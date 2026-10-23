# Gerador de carga no Windows, espelhando tests/carga/carga.js (k6). Rodado por tests/carga/carga_windows.ps1, que
# sobe a 2a instancia do Apache (porta 8098, banco gktgym_teste). Senha do administrador temporario em $env:CARGA_SENHA.
#   pwsh tests/carga/gerador_carga.ps1 -Modo recepcao -Pessoas 30 -Segundos 120 -TotalEsperado 2000
#   pwsh tests/carga/gerador_carga.ps1 -Modo logins -PorSegundo 5 -Segundos 60
param(
    [ValidateSet('recepcao', 'logins')][string]$Modo = 'recepcao',
    [int]$Pessoas = 10,
    [int]$Segundos = 60,
    [int]$PorSegundo = 5,
    [string]$Base = 'http://127.0.0.1:8098/Gkt/Sistema-Gerenciamento-Alunos',
    [string]$Usuario = 'carga_win',
    [int]$TotalEsperado = -1
)
$ErrorActionPreference = 'Stop'
$senha = $env:CARGA_SENHA
if (-not $senha) { throw 'defina CARGA_SENHA' }

# trava de seguranca: so roda se o dashboard mostrar o total de alunos do banco de teste
if ($TotalEsperado -ge 0) {
    $web = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $p = Invoke-WebRequest "$Base/index.php" -WebSession $web -SkipHttpErrorCheck
    $t = [regex]::Match($p.Content, 'name="csrf_token" value="([^"]+)"').Groups[1].Value
    Invoke-WebRequest "$Base/BACKEND/CONTROLLER/actions.php" -Method Post -WebSession $web -MaximumRedirection 0 -SkipHttpErrorCheck -ErrorAction SilentlyContinue `
        -Body @{ csrf_token = $t; inputUser = $Usuario; inputUserPass = $senha } | Out-Null
    $d = Invoke-WebRequest "$Base/sistema.php?pagina=DASHBOARD" -WebSession $web -SkipHttpErrorCheck -MaximumRedirection 0
    $total = [regex]::Match($d.Content, '(\d+)\s*</p>\s*<p>\s*Total de alunos').Groups[1].Value
    if ($total -ne "$TotalEsperado") { throw "Dashboard mostra '$total' alunos, esperado $TotalEsperado do banco de teste. Abortado." }
    "trava ok: banco de teste com $total alunos"
}

$inicio = Get-Date
if ($Modo -eq 'logins') {
    # PorSegundo logins por segundo: cada trabalhador faz 1 login a cada 2 s com sessao nova
    $trabalhadores = $PorSegundo * 2
    $medidas = 1..$trabalhadores | ForEach-Object -ThrottleLimit $trabalhadores -Parallel {
        $base = $using:Base; $usuario = $using:Usuario; $senha = $using:senha
        $fim = (Get-Date).AddSeconds($using:Segundos)
        Start-Sleep -Milliseconds (Get-Random -Maximum 2000)
        while ((Get-Date) -lt $fim) {
            $proximo = (Get-Date).AddSeconds(2)
            $web = New-Object Microsoft.PowerShell.Commands.WebRequestSession
            $sw = [Diagnostics.Stopwatch]::StartNew()
            try {
                $p = Invoke-WebRequest "$base/index.php" -WebSession $web -SkipHttpErrorCheck
                $t = [regex]::Match($p.Content, 'name="csrf_token" value="([^"]+)"').Groups[1].Value
                $sw.Restart()
                $r = Invoke-WebRequest "$base/BACKEND/CONTROLLER/actions.php" -Method Post -WebSession $web -MaximumRedirection 0 -SkipHttpErrorCheck -ErrorAction SilentlyContinue `
                    -Body @{ csrf_token = $t; inputUser = $usuario; inputUserPass = $senha }
                $ok = $r.StatusCode -eq 302 -and "$($r.Headers.Location)" -like '*sistema.php*'
                $st = [int]$r.StatusCode
            } catch { $ok = $false; $st = 0 }
            $sw.Stop()
            [pscustomobject]@{ Tipo = 'login'; Ms = $sw.Elapsed.TotalMilliseconds; Status = $st; Ok = $ok }
            $falta = ($proximo - (Get-Date)).TotalMilliseconds
            if ($falta -gt 0) { Start-Sleep -Milliseconds $falta }
        }
    }
} else {
    $medidas = 1..$Pessoas | ForEach-Object -ThrottleLimit $Pessoas -Parallel {
        $base = $using:Base; $usuario = $using:Usuario; $senha = $using:senha; $pessoa = $_
        $fim = (Get-Date).AddSeconds($using:Segundos)
        $web = New-Object Microsoft.PowerShell.Commands.WebRequestSession
        function Medir([string]$tipo, [scriptblock]$pedido, [string]$deveConter) {
            $sw = [Diagnostics.Stopwatch]::StartNew()
            try { $r = & $pedido; $st = [int]$r.StatusCode } catch { $r = $null; $st = 0 }
            $sw.Stop()
            $ok = $st -eq 200 -and (-not $deveConter -or $r.Content.Contains($deveConter))
            [pscustomobject]@{ Tipo = $tipo; Ms = $sw.Elapsed.TotalMilliseconds; Status = $st; Ok = $ok; Corpo = if ($tipo -eq 'form') { $r.Content } else { $null } }
        }
        function Cpf {
            $d = 1..9 | ForEach-Object { Get-Random -Maximum 10 }
            foreach ($t in 9, 10) { $s = 0; for ($i = 0; $i -lt $t; $i++) { $s += $d[$i] * ($t + 1 - $i) }; $d += ((10 * $s) % 11) % 10 }
            -join $d
        }
        Start-Sleep -Milliseconds (Get-Random -Maximum 3000) # chegada escalonada
        $p = Invoke-WebRequest "$base/index.php" -WebSession $web -SkipHttpErrorCheck
        $t = [regex]::Match($p.Content, 'name="csrf_token" value="([^"]+)"').Groups[1].Value
        Invoke-WebRequest "$base/BACKEND/CONTROLLER/actions.php" -Method Post -WebSession $web -MaximumRedirection 0 -SkipHttpErrorCheck -ErrorAction SilentlyContinue `
            -Body @{ csrf_token = $t; inputUser = $usuario; inputUserPass = $senha } | Out-Null
        $iteracao = 0
        while ((Get-Date) -lt $fim) {
            Medir 'pagina' { Invoke-WebRequest "$base/sistema.php?pagina=DASHBOARD" -WebSession $web -SkipHttpErrorCheck -MaximumRedirection 0 } 'Total de alunos' | Select-Object Tipo, Ms, Status, Ok
            Start-Sleep 1
            foreach ($filtro in '', 'alunos_pendentes', 'alunos_pagos', 'plano_2', 'alunos_mulheres') {
                Medir 'pagina' { Invoke-WebRequest "$base/sistema.php?pagina=ALUNOS&filtrando=$filtro" -WebSession $web -SkipHttpErrorCheck -MaximumRedirection 0 } '' | Select-Object Tipo, Ms, Status, Ok
            }
            Medir 'pagina' { Invoke-WebRequest "$base/sistema.php?pagina=ALUNOS&p=2" -WebSession $web -SkipHttpErrorCheck -MaximumRedirection 0 } '' | Select-Object Tipo, Ms, Status, Ok
            Start-Sleep 1
            Medir 'pagina' { Invoke-WebRequest "$base/sistema.php?pagina=TURMAS&filtrando=1" -WebSession $web -SkipHttpErrorCheck -MaximumRedirection 0 } '' | Select-Object Tipo, Ms, Status, Ok
            Start-Sleep 1
            if ((Get-Random -Maximum 4) -eq 0) { # 1 em cada 4 voltas cadastra um aluno, como a recepcao faria
                $form = Medir 'form' { Invoke-WebRequest "$base/sistema.php?pagina=CADASTRAR" -WebSession $web -SkipHttpErrorCheck -MaximumRedirection 0 } 'csrf_token'
                $form | Select-Object Tipo, Ms, Status, Ok
                $tk = [regex]::Match("$($form.Corpo)", 'name="csrf_token" value="([^"]+)"').Groups[1].Value
                $sw = [Diagnostics.Stopwatch]::StartNew()
                $r = Invoke-WebRequest "$base/BACKEND/CONTROLLER/gravarAluno.php" -Method Post -WebSession $web -MaximumRedirection 0 -SkipHttpErrorCheck -ErrorAction SilentlyContinue -Body @{
                    csrf_token = $tk; nome = "CARGA $pessoa-$iteracao"; telefone = '61999990000'
                    email = "carga.$pessoa.$iteracao.$([guid]::NewGuid().ToString('N').Substring(0,8))@exemplo.invalid"
                    cpf = (Cpf); data_nascimento = '1995-03-20'; genero = 'masculino'; cep = '70000000'; endereco = 'Rua Ficticia 1'
                    'modalidades[]' = '1'; 'plano-pagamento' = '1'; 'frequencia-dias' = '2'; status = '1'; ciencia_privacidade = '1'
                }
                $sw.Stop()
                $volta = Invoke-WebRequest "$base/sistema.php?pagina=CADASTRAR" -WebSession $web -SkipHttpErrorCheck -MaximumRedirection 0
                [pscustomobject]@{ Tipo = 'cadastro'; Ms = $sw.Elapsed.TotalMilliseconds; Status = [int]$r.StatusCode
                    Ok = [int]$r.StatusCode -eq 302 -and $volta.Content.Contains('Aluno cadastrado com sucesso') }
            }
            Start-Sleep 2
            $iteracao++
        }
    }
}
$duracao = ((Get-Date) - $inicio).TotalSeconds

function P([double[]]$v, [double]$q) { if (-not $v) { return 0 }; $s = $v | Sort-Object; $s[[math]::Min($s.Count - 1, [math]::Floor($q * $s.Count))] }
"== $Modo  ($(if ($Modo -eq 'logins') { "$PorSegundo logins/s" } else { "$Pessoas pessoas" }), $([math]::Round($duracao)) s)"
$medidas | Group-Object Tipo | ForEach-Object {
    $ms = [double[]]($_.Group | ForEach-Object Ms)
    $erros = @($_.Group | Where-Object { -not $_.Ok }).Count
    '{0,-9} {1,6} pedidos  {2,5:N1}/s  mediana {3,6:N0} ms  p95 {4,6:N0} ms  max {5,6:N0} ms  erros {6} ({7:P1})' -f `
        $_.Name, $_.Count, ($_.Count / $duracao), (P $ms 0.5), (P $ms 0.95), ($ms | Measure-Object -Maximum).Maximum, $erros, ($erros / $_.Count)
}
$falhas = $medidas | Where-Object { -not $_.Ok } | Group-Object Status | ForEach-Object { "HTTP $($_.Name): $($_.Count)" }
if ($falhas) { 'falhas por status: ' + ($falhas -join ', ') }
