<!--
COMO MANTER ESTE ARQUIVO
- Toda pasta nova de testes ou pré-requisito novo (serviço, variável, banco) aparece aqui.
- Os comandos devem funcionar copiando e colando no PowerShell, na raiz do projeto.
- Exemplo de teste bom:  testCpfRepetidoEhRecusado — o nome diz a regra; uma regra por teste.
- Exemplo de teste ruim: testCadastro2 — não diz o que está sendo garantido.
-->

# Testes automatizados

| Pasta | O que garante | Precisa de |
|---|---|---|
| `Unidade/` | Regras puras: CPF, validação do formulário, máscara de CPF, política de senha, escape XSS, CSRF | nada (o CI do GitHub roda estes) |
| `Integracao/` | Cadastro, listas, filtros, paginação, edição, exportação, anonimização, auditoria, permissões do banco, scripts de terminal | SQL Server com o banco `gktgym_teste` |
| `Funcional/` | O sistema rodando: login, bloqueio por tentativas, sessão, CSRF, telas, cadastrar, editar, exportar, excluir, sair | `gktgym_teste` (o teste sobe um servidor próprio na porta 8099) |
| `Apache/` | Regras do `.htaccess`, cabeçalhos e configuração do Apache | Apache do XAMPP rodando (só faz leituras) |

**Os testes nunca usam o banco real.** O `bootstrap.php` recusa qualquer banco cujo nome não termine em `_teste`,
e o sistema é apontado para ele pela variável `GKT_DB_DATABASE` (lida em `BACKEND/CONNECTION/config.php`).
Grupos sem o que precisam são **pulados** (aparecem como "skipped"), não falham.

## Como rodar

Sempre com o PHP do XAMPP (o `php` do PATH não tem o driver do SQL Server):

```powershell
# 1. uma vez (e sempre que mudar algo em DB/): recria o banco de teste do zero
pwsh tests/preparar_banco_teste.ps1

# 2. todos os testes
C:\xampp\php\php.exe vendor\bin\phpunit

# só um grupo
C:\xampp\php\php.exe vendor\bin\phpunit --testsuite unidade

# só um arquivo ou um teste
C:\xampp\php\php.exe vendor\bin\phpunit tests\Integracao\CadastroTest.php
C:\xampp\php\php.exe vendor\bin\phpunit --filter testCpfRepetidoEhRecusado
```

## Como escrever um teste novo

- Sem banco: `final class XTest extends PHPUnit\Framework\TestCase` em `Unidade/`.
- Com banco: `extends TesteComBanco` (as tabelas de alunos começam vazias; use `$this->cadastrar([...])`).
- Pelo navegador: `extends TesteFuncional` (`$this->entrar()`, `$this->nav->get/post(...)`, `$this->tokenDaSessao()`).
- Use os CPFs de `TesteComBanco::CPFS` e e-mails `@exemplo.invalid`. **Nunca dados reais.**
- Achou um bug? Escreva primeiro o teste que falha, depois corrija o código.

## O que ainda não é testado automaticamente

- Aparência das telas (CSS, responsividade) e o JavaScript do botão de mostrar senha.
- `DB/backup.ps1` (precisa de permissão de escrita do serviço do SQL Server).
- Testes de integração e funcionais no CI do GitHub (precisariam de um SQL Server no runner).
