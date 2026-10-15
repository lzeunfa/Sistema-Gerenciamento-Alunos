---
name: revisar-pr
description: Revisa um Pull Request ou branch do Sistema GKT com olhar de auditor (segurança, LGPD, regressões, código morto, duplicação, valores mágicos, documentação). Use quando pedirem para revisar PR, revisar branch, revisar mudanças antes do merge, ou com /revisar-pr <número ou branch>.
---

<!--
COMO MANTER ESTA SKILL
- O "description" acima decide QUANDO a skill é usada: descreva a situação com as palavras que a equipe usa.
- O corpo é um procedimento: passos numerados, cada um com o que fazer e o que procurar.
- Regra do projeto nova? Coloque no CLAUDE.md (vale sempre). Aqui só entra o que é específico de REVISÃO.
- Exemplo de item bom:  "Formulário novo sem csrfCampo() → bloqueante."
- Exemplo de item ruim: "Verifique se o código está seguro."
Baseado em: https://akitaonrails.com/2026/05/30/boas-praticas-projetos-codigo-aberto-llm-o-minimo/
-->

# Revisão de Pull Request — Sistema GKT

Você é o revisor. **Não confie na descrição do PR**: ela diz o que o autor *acha* que fez. Audite o código.

## 1. Pegar as mudanças

- PR do GitHub: `gh pr view <n> --json title,body,files` e `gh pr diff <n>` (se `gh` não estiver instalado:
  `git fetch origin pull/<n>/head:pr-<n>` e `git diff main...pr-<n>`).
- Branch: `git fetch origin && git diff origin/main...origin/<branch>`.
- Leia os arquivos alterados **inteiros**, não só o diff: o problema costuma estar no que não mudou.

## 2. Auditar (em ordem de gravidade)

**Segurança (bloqueante)** — regras completas no `CLAUDE.md`:
- Dado do banco ou do usuário impresso sem `e()` → XSS.
- Formulário que altera dados sem `csrfCampo()`, ou ação sem `exigirPostComCsrf()`.
- SQL montado com concatenação de entrada do usuário.
- Página interna sem `trava.php`; `session_start()` direto.
- `onclick=`/`<script>` inline (quebra na CSP).
- Validação só no HTML, sem validação no servidor.
- Arquivo novo acessível pelo navegador que não deveria (confira o `.htaccess`).
- Exceção ou erro de banco mostrado ao usuário.

**LGPD (bloqueante)**:
- Dado pessoal novo sem atualização de `privacidade.php` e `DOC/SEGURANCA_LGPD.md`.
- CPF completo em listagem; dado pessoal em `registrarAuditoria()`, `error_log()` ou mensagem.
- Dados reais de pessoas em `.sql`, testes ou exemplos.

**Regressões**:
- Fluxos existentes que podem quebrar: login, sessão expirada, cadastro, filtros, paginação, sair.
- Mudança de banco sem `DB/migracao_*.sql` idempotente, ou que exige permissão além de leitura/escrita.
- Função alterada: procure (Grep) todos os lugares que a chamam.

**Qualidade**:
- Código morto (funções, variáveis, CSS e arquivos sem uso; código comentado).
- Duplicação (lógica copiada que já existe em `funcoes.php` ou `seguranca.php`).
- Valores mágicos (números e textos soltos que deveriam ser constante ou vir do banco).
- Cobertura: o autor testou o caso de erro? Há passo de teste reproduzível?

**Documentação**:
- `CHANGELOG.md` atualizado em `[Não publicado]`; README/CLAUDE.md se mudou instalação ou regra;
  `DOC/DECISOES.md` se houve decisão de arquitetura.

## 3. Verificar de verdade

- Rode a checagem de sintaxe e `composer analise` (comandos no `CLAUDE.md`).
- Se a mudança é visível, teste no navegador em http://localhost/Gkt/Sistema-Gerenciamento-Alunos/,
  incluindo um caso de erro e um ataque simples (ex.: `<script>` num campo de texto).

## 4. Relatório

Responda em português, neste formato:

```
## Veredito: APROVAR | APROVAR COM AJUSTES | PEDIR MUDANÇAS

### Bloqueantes
- arquivo.php:linha — problema → como corrigir

### Sugestões
- ...

### O que foi verificado
- comandos rodados e fluxos testados (e o que NÃO foi possível verificar)
```

Depois, **pergunte** ao usuário qual caminho seguir. Não faça sozinho:
1. corrigir no próprio PR;
2. mergear e corrigir em commit seguinte;
3. recusar: escreva um comentário educado explicando o motivo, para o usuário postar.

Nunca faça merge, push ou comentário no GitHub sem o usuário pedir explicitamente.
