# Teste de carga no Windows (XAMPP), sem Docker: o mesmo cenario do k6 (tests/carga/carga.js).
# Recria o banco gktgym_teste com alunos ficticios, sobe uma 2a instancia do Apache do XAMPP na porta 8098 ligada a
# ele (tests/carga/apache-carga.conf), mede e desliga tudo no fim. O Apache normal e o banco gktgym nao sao tocados.
#   pwsh tests/carga/carga_windows.ps1                      30 pessoas por 2 minutos + 5 logins/s por 1 minuto
#   pwsh tests/carga/carga_windows.ps1 -Pessoas 100 -Segundos 90
param([int]$Pessoas = 30, [int]$Segundos = 120, [int]$LoginsPorSegundo = 5, [int]$Alunos = 2000)
$ErrorActionPreference = 'Stop'
$raiz = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$php = 'C:\xampp\php\php.exe'
$apacheRaiz = 'C:\xampp\apache'
$base = 'http://127.0.0.1:8098/' + $raiz.Substring('C:\xampp\htdocs\'.Length).Replace('\', '/')
$usuario = 'carga_win'

Push-Location $raiz
$apache = $null
try {
    pwsh tests/preparar_banco_teste.ps1 | Select-Object -Last 1
    $env:GKT_DB_DATABASE = 'gktgym_teste'
    & $php tests/carga/popular_volume.php $Alunos --confirmo-banco-de-teste | Select-Object -Last 1
    $env:CARGA_SENHA = 'Carga-' + [guid]::NewGuid().ToString('N').Substring(0, 20) + '-4'
    $env:GKT_NOVA_SENHA = $env:CARGA_SENHA
    & $php DB/criar_admin.php $usuario 'CARGA WINDOWS' 'carga.win@exemplo.invalid' | Out-Null
    Remove-Item Env:GKT_NOVA_SENHA

    $apache = Start-Process "$apacheRaiz\bin\httpd.exe" -ArgumentList '-f', "`"$raiz\tests\carga\apache-carga.conf`"" -WindowStyle Hidden -PassThru
    for ($i = 0; $i -lt 50 -and -not (Test-NetConnection 127.0.0.1 -Port 8098 -InformationLevel Quiet -WarningAction SilentlyContinue); $i++) { Start-Sleep -Milliseconds 200 }

    pwsh tests/carga/gerador_carga.ps1 -Modo recepcao -Pessoas $Pessoas -Segundos $Segundos -Base $base -Usuario $usuario -TotalEsperado $Alunos
    pwsh tests/carga/gerador_carga.ps1 -Modo logins -PorSegundo $LoginsPorSegundo -Segundos 60 -Base $base -Usuario $usuario
} finally {
    if ($apache) {
        # o httpd do Windows tem um processo filho que atende os pedidos
        Get-CimInstance Win32_Process -Filter "ParentProcessId=$($apache.Id)" | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
        Stop-Process -Id $apache.Id -Force -ErrorAction SilentlyContinue
        Remove-Item "$apacheRaiz\logs\gkt-carga.pid" -ErrorAction SilentlyContinue
    }
    & $php -r "require 'BACKEND/CONNECTION/config.php'; Database::getConexao()->exec(""DELETE FROM ADMINISTRACAO WHERE ADMLOGIN = '$usuario'"");"
    Remove-Item Env:CARGA_SENHA, Env:GKT_DB_DATABASE -ErrorAction SilentlyContinue
    Pop-Location
}
