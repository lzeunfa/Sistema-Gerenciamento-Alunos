-- Desempenho com muitos alunos (teste de volume com 20 e 100 mil alunos ficticios, ver DOC/RELATORIO_DOCKER_E_SEGURANCA.md).
-- O SQL Server nao cria indice sozinho para chave estrangeira; estas colunas sao usadas em todo JOIN e filtro.
-- Pode ser executado mais de uma vez sem problemas.
USE [gktgym]
GO

-- inscricoes de um aluno: lista, contagem, edicao (DELETE/UPDATE por matricula) e exportacao
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_ALUNOS_INSCRICAO_ALUNOMAT' AND object_id = OBJECT_ID('dbo.ALUNOS_INSCRICAO'))
    CREATE INDEX [IX_ALUNOS_INSCRICAO_ALUNOMAT] ON [dbo].[ALUNOS_INSCRICAO] ([ALUNOMAT])
        INCLUDE ([ALUNOMOD], [ALUNOPLANO], [ALUNOOPCAO], [DATACADASTRO])
GO

-- tela Turmas (filtro por modalidade)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_ALUNOS_INSCRICAO_ALUNOMOD' AND object_id = OBJECT_ID('dbo.ALUNOS_INSCRICAO'))
    CREATE INDEX [IX_ALUNOS_INSCRICAO_ALUNOMOD] ON [dbo].[ALUNOS_INSCRICAO] ([ALUNOMOD]) INCLUDE ([ALUNOMAT])
GO

-- filtros por situacao (pagos/pendentes) e retencao de inativos (DB/anonimizar_antigos.php)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_ALUNOS_ALUSTATUS' AND object_id = OBJECT_ID('dbo.ALUNOS'))
    CREATE INDEX [IX_ALUNOS_ALUSTATUS] ON [dbo].[ALUNOS] ([ALUSTATUS]) INCLUDE ([ALUDATAINATIVACAO], [ALUANONIMIZADO])
GO

-- protecao contra forca bruta: limpeza das tentativas antigas por data
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_LOGIN_TENTATIVAS_DATAHORA' AND object_id = OBJECT_ID('dbo.LOGIN_TENTATIVAS'))
    CREATE INDEX [IX_LOGIN_TENTATIVAS_DATAHORA] ON [dbo].[LOGIN_TENTATIVAS] ([DATAHORA])
GO
