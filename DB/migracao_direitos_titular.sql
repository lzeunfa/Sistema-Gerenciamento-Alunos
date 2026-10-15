-- Direitos do titular (LGPD art. 18) e politica de retencao (art. 15 e 16).
-- Rodar depois de migracao_seguranca.sql, com um usuario que possa alterar a estrutura (o gkt_app nao pode).
-- Pode ser executado mais de uma vez sem problemas.
USE [gktgym]
GO

-- Situacao para quem encerrou a matricula; a contagem dos 5 anos de guarda comeca na data de inativacao
IF NOT EXISTS (SELECT 1 FROM [dbo].[ALUNOS_STATUS] WHERE [STATUSALU] = 'INATIVO')
    INSERT INTO [dbo].[ALUNOS_STATUS] ([STATUSALU]) VALUES ('INATIVO')
GO

-- Quando o aluno passou para INATIVO (preenchido pelo sistema ao editar)
IF COL_LENGTH('dbo.ALUNOS', 'ALUDATAINATIVACAO') IS NULL
    ALTER TABLE [dbo].[ALUNOS] ADD [ALUDATAINATIVACAO] [datetime] NULL
GO

-- Quando os dados pessoais foram anonimizados (pedido de eliminacao ou fim do prazo de guarda)
IF COL_LENGTH('dbo.ALUNOS', 'ALUANONIMIZADO') IS NULL
    ALTER TABLE [dbo].[ALUNOS] ADD [ALUANONIMIZADO] [datetime] NULL
GO
