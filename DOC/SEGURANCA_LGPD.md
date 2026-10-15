# Segurança e LGPD — Sistema GKT

Revisão feita em 14/10/2026 com base na LGPD (Lei 13.709/2018), nas resoluções da ANPD (CD/ANPD nº 2/2022 —
agentes de pequeno porte; nº 15/2024 — comunicação de incidentes), no Guia de Segurança da Informação da ANPD
para pequenos agentes e no OWASP Top 10:2025.

> Este documento é técnico e não substitui orientação jurídica. Os itens marcados como **[ACADEMIA]** dependem
> de decisões ou informações da GKT, não de código.

## 1. Falhas encontradas e corrigidas

| # | Gravidade | Falha | OWASP 2025 | Correção |
|---|---|---|---|---|
| 1 | Crítica | `.env` (senha do banco) e pasta `.git` podiam ser baixados pelo navegador | A02 Configuração | `.htaccess` devolve 404 para arquivos ocultos, `DB/`, `DOC/`, `BACKEND/CONNECTION`, páginas internas e `.sql/.md/.ini/.log/.bak`. Senha do banco trocada. |
| 2 | Alta | XSS armazenado: nome, plano etc. eram impressos sem escape | A05 Injeção | Função `e()` em toda saída de dados |
| 3 | Alta | Nenhuma proteção CSRF (cadastro, login, logout) | A01 Acesso | Token CSRF em todo formulário; logout só por POST; cookie `SameSite=Lax` |
| 4 | Alta | Login sem limite de tentativas | A07 Autenticação | Bloqueio de 15 min após 5 erros no login ou 20 no mesmo IP |
| 5 | Alta | Cookie de sessão sem `HttpOnly`/`SameSite`, sem modo estrito (session fixation) | A07 | `seguranca.php` configura a sessão; `session_regenerate_id` no login |
| 6 | Média | Erros do PHP exibidos na tela (caminhos, SQL) | A10 Exceções | Erros só no log; mensagem genérica ao usuário |
| 7 | Média | Sem validação no servidor no cadastro (CPF com pontuação nem cabia no banco) | A06 Design | CPF com dígito verificador, e-mail, datas, tamanhos e IDs numéricos validados |
| 8 | Média | Usuário do banco era `db_owner` | A01 | Apenas leitura e escrita; não altera estrutura nem apaga auditoria. `sa` desativado |
| 9 | Média | Sem registro de acessos | A09 Logs | Tabela `AUDITORIA` (login, falhas, bloqueios, cadastro, logout, CSRF) |
| 10 | Média | Sem cabeçalhos de segurança | A02 | CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS (com HTTPS) |
| 11 | Baixa | Versões do Apache/PHP expostas nos cabeçalhos | A02 | `ServerTokens Prod`, `ServerSignature Off`, `expose_php Off` |
| 12 | Baixa | Senhas em bcrypt | A04 Criptografia | Argon2id (recomendação OWASP); senhas antigas são convertidas no próximo login |
| 13 | Baixa | Diferença de tempo revelava se o usuário existia | A07 | Verificação contra hash falso para tempo constante |
| 14 | Baixa | `test.php` com caminho fixo e scripts de manutenção acessíveis pela web | A02 | `test.php` removido; scripts de `DB/` só rodam pelo terminal |

Configurações feitas **apenas neste computador** (fora do Git), com backup `*.bak-antes-seguranca`:
`C:\xampp\php\php.ini`, `C:\xampp\apache\conf\httpd.conf`, `extra\httpd-default.conf`, `extra\httpd-ssl.conf`.
O Apache agora só atende `127.0.0.1` (o próprio computador). Para acessar de outro aparelho da rede, volte
`Listen 80` no `httpd.conf` — mas só faça isso com HTTPS.

## 2. Como aplicar em outra máquina (equipe)

1. Rodar no banco, nesta ordem: `DB/migracao_login.sql`, `DB/migracao_seguranca.sql` e `DB/migracao_direitos_titular.sql`.
2. Executar os comandos de PERMISSÕES do fim de `migracao_seguranca.sql` (ajustando o nome do usuário do banco).
3. Criar o administrador: `C:\xampp\php\php.exe DB/criar_admin.php <login>` (a senha é pedida no terminal, mínimo 12 caracteres).
4. Conferir que `http://localhost/.../.env` responde 404.

## 3. Situação da LGPD

| Exigência | Artigo | Situação |
|---|---|---|
| Base legal para cada uso dos dados | 7º | Definida na Política: execução de contrato, obrigação legal, legítimo interesse |
| Informar o titular (transparência) | 9º | `privacidade.php` + confirmação obrigatória no cadastro (`ALUCIENCIAPRIVACIDADE`) **[ACADEMIA: preencher os campos entre colchetes]** |
| Dados de crianças e adolescentes | 14 | Responsável (nome e CPF) obrigatório para menores de 18; confirmação de autorização no cadastro |
| Minimização / necessidade | 6º, III | CPF mascarado nas listas; dados de responsável não são guardados para adultos |
| Segurança | 46, 47, 49 | Itens da seção 1 |
| Registro das operações | 37 | Seção 4 abaixo + tabela `AUDITORIA` |
| Canal com o titular / encarregado | 41 | Pequeno porte pode dispensar o encarregado, mas **precisa de um canal** **[ACADEMIA: definir e-mail/telefone]** |
| Direitos do titular — acesso e portabilidade | 18, II e V | Botão **Exportar** (listas de Alunos/Turmas e tela de edição) baixa um JSON com todos os dados e inscrições do aluno. Prazo de resposta: 15 dias (art. 19) |
| Direitos do titular — correção | 18, III | Botão **Editar**: mesmas validações do cadastro; responsável legal obrigatório para menores e descartado para adultos |
| Direitos do titular — eliminação | 18, IV e VI; 16 | Botão **Excluir** com tela de confirmação: **anonimiza** (nome `ANONIMIZADO`; CPF, e-mail, telefone e endereço trocados por valores únicos sem significado; CEP e responsável apagados; nascimento reduzido ao ano). Matrícula, sexo e inscrições ficam para estatística. Não pode ser desfeito |
| Prazo de guarda e eliminação | 15, 16 | Política define 5 anos após o fim da matrícula. Situação `INATIVO` grava `ALUDATAINATIVACAO`; `DB/anonimizar_antigos.php --executar` anonimiza quem está inativo há mais de 5 anos. **[ACADEMIA: agendar no Agendador de Tarefas, 1x por mês]** |
| Comunicação de incidentes | 48 | Seção 5 abaixo |
| Disponibilidade (backup) | 46 | `DB/backup.ps1` (com verificação). **[ACADEMIA: agendar e guardar cópia fora do PC]** |

## 4. Registro das operações de tratamento (art. 37, modelo simplificado)

| Operação | Dados | Titulares | Finalidade | Base legal | Quem acessa | Guarda |
|---|---|---|---|---|---|---|
| Matrícula | nome, CPF, nascimento, sexo, telefone, e-mail, CEP, endereço | Alunos | Contrato e turmas | Art. 7º, V | Administradores | Matrícula + 5 anos |
| Matrícula de menor | os acima + nome e CPF do responsável | Alunos menores e responsáveis | Contrato, com autorização | Art. 14 | Administradores | Matrícula + 5 anos |
| Cobrança | plano, situação de pagamento | Alunos | Contrato / fiscal | Art. 7º, V e II | Administradores | 5 anos |
| Login de administradores | login, e-mail, senha (hash), IP | Funcionários | Segurança | Art. 7º, IX | Sistema | Enquanto ativo |
| Auditoria | matrícula/login, ação, IP, data | Funcionários | Segurança e prestação de contas | Art. 7º, IX | Administradores | 5 anos (Res. 15/2024) |
| Exportação a pedido do titular | todos os dados do aluno | Alunos e responsáveis | Atender acesso/portabilidade | Art. 18 | Administradores (arquivo entregue ao titular) | Não fica cópia no sistema |
| Dados anonimizados | matrícula, sexo, ano de nascimento, inscrições | Ex-alunos (não identificáveis) | Estatística | Art. 12 e 16, IV | Administradores | Indeterminado |

## 5. Se houver um incidente (vazamento, acesso indevido, perda de dados)

1. Conter: desligar o acesso (parar o Apache), trocar senhas do banco e dos administradores.
2. Investigar pela tabela `AUDITORIA` e pelos logs (`C:\xampp\php\logs`, `C:\xampp\apache\logs`).
3. Se puder causar **risco ou dano relevante** aos titulares (ex.: CPF, dados de menores, senhas):
   comunicar a ANPD (formulário no site gov.br/anpd) e os alunos afetados em **3 dias úteis**
   (**6 dias úteis** para agente de pequeno porte), a partir de saber que dados pessoais foram afetados.
4. Registrar o incidente, mesmo que não seja comunicado, e guardar o registro por **5 anos**.

## 6. Recomendações que ficam para a academia ou para a produção

- **HTTPS obrigatório** em produção (o cookie passa a ser `Secure` e o HSTS liga automaticamente).
- **Atualizar a pilha**: XAMPP 8.2.12 traz PHP 8.2.12 e Apache 2.4.58 de 2023, com falhas conhecidas corrigidas
  em versões mais novas. Aplicar também a última atualização cumulativa (CU) do SQL Server 2022.
- **Criptografar o disco** (BitLocker) do computador do banco e dos backups.
- **`DB/script.sql` no GitHub público** contém nome, CPF, e-mail e telefone de um aluno de exemplo. Se for pessoa
  real, remover o arquivo e limpar o histórico do repositório (ou tornar o repositório privado).
- Treinar quem usa o sistema: não compartilhar senha, sair ao terminar, desconfiar de links.
