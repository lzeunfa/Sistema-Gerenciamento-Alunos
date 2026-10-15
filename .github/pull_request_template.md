<!--
COMO PREENCHER (este texto não aparece no PR publicado)
- Explique o PORQUÊ, não só o quê. O revisor vai ler o código; a descrição dá o contexto.
- Marque só o que você realmente fez. Item que não se aplica: risque com ~~texto~~ e diga por quê.
- Exemplo bom de resumo:  "Cadastro aceitava CPF com pontuação e quebrava no banco. Agora remove a pontuação
                           e valida o dígito verificador antes de gravar."
- Exemplo ruim:           "ajustes no cadastro"
-->

## Resumo
<!-- O que muda e por quê, em 2 a 4 frases. Link da issue, se houver. -->

## Como testar
<!-- Passos para o revisor reproduzir. Ex.: 1. Logar como admin  2. Cadastrar aluno com CPF 123... -->

## Checklist
- [ ] Testei o fluxo no navegador (incluindo o caso de erro)
- [ ] Dados impressos no HTML passam por `e()` e formulários novos têm `csrfCampo()`
- [ ] SQL só com `prepare()` e parâmetros
- [ ] Sem JavaScript inline (`onclick=`, `<script>` sem `src`)
- [ ] Mudança no banco tem `DB/migracao_<assunto>.sql` idempotente
- [ ] Dado pessoal novo? Atualizei `privacidade.php` e `DOC/SEGURANCA_LGPD.md`
- [ ] `CHANGELOG.md` atualizado na seção `[Não publicado]`
- [ ] Nenhum dado real de aluno, senha ou `.env` no commit
