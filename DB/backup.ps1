# Backup do banco gktgym (LGPD art. 46: disponibilidade dos dados).
# Uso: pwsh DB/backup.ps1 [-Destino C:\GKT-Backups] [-ManterDias 30]
# Os arquivos ficam FORA da pasta do site. Guarde uma copia em outro disco/nuvem e, de preferencia,
# em unidade criptografada (BitLocker), pois o backup contem dados pessoais.
param(
    [string]$Destino = 'C:\GKT-Backups',
    [int]$ManterDias = 30,
    [string]$Servidor = 'localhost\SQLEXPRESS'
)
$ErrorActionPreference = 'Stop'

New-Item -ItemType Directory -Force $Destino | Out-Null
$arquivo = Join-Path $Destino ("gktgym_{0}.bak" -f (Get-Date -Format 'yyyyMMdd_HHmmss'))

sqlcmd -S $Servidor -E -C -b -Q "BACKUP DATABASE [gktgym] TO DISK = N'$arquivo' WITH INIT, CHECKSUM; RESTORE VERIFYONLY FROM DISK = N'$arquivo' WITH CHECKSUM;"
if ($LASTEXITCODE -ne 0) { throw "Backup falhou." }
Write-Output "Backup criado e verificado: $arquivo"

# apaga backups antigos (minimizacao: nao guardar dados pessoais alem do necessario)
Get-ChildItem $Destino -Filter 'gktgym_*.bak' |
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$ManterDias) } |
    Remove-Item
