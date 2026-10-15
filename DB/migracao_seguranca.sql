-- Seguranca e LGPD: auditoria, protecao contra forca bruta e dados de responsavel/ciencia.
-- Rodar depois de migracao_login.sql. Pode ser executado mais de uma vez sem problemas.
USE [gktgym]
GO

-- Registro de quem fez o que e quando (LGPD art. 37 e 46). Guardar por pelo menos 5 anos (Res. CD/ANPD 15/2024).
IF OBJECT_ID('dbo.AUDITORIA') IS NULL
CREATE TABLE [dbo].[AUDITORIA](
    [AUDCOD] [bigint] IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [DATAHORA] [datetime2](0) NOT NULL DEFAULT (SYSDATETIME()),
    [USUARIO] [varchar](50) NOT NULL,
    [ACAO] [varchar](40) NOT NULL,
    [DETALHE] [varchar](255) NULL,
    [IP] [varchar](45) NULL
)
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_AUDITORIA_DATAHORA')
CREATE INDEX [IX_AUDITORIA_DATAHORA] ON [dbo].[AUDITORIA] ([DATAHORA])
GO

-- Tentativas de login erradas, usadas para bloquear forca bruta
IF OBJECT_ID('dbo.LOGIN_TENTATIVAS') IS NULL
CREATE TABLE [dbo].[LOGIN_TENTATIVAS](
    [TENTCOD] [bigint] IDENTITY(1,1) NOT NULL PRIMARY KEY,
    [CHAVE] [varchar](150) NOT NULL,
    [DATAHORA] [datetime] NOT NULL DEFAULT (GETDATE())
)
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_LOGIN_TENTATIVAS_CHAVE')
CREATE INDEX [IX_LOGIN_TENTATIVAS_CHAVE] ON [dbo].[LOGIN_TENTATIVAS] ([CHAVE], [DATAHORA])
GO

-- Responsavel legal de menores (LGPD art. 14) e data em que o titular foi informado da Politica de Privacidade (art. 9)
IF COL_LENGTH('dbo.ALUNOS', 'ALURESPNOME') IS NULL
    ALTER TABLE [dbo].[ALUNOS] ADD [ALURESPNOME] [varchar](100) NULL
GO
IF COL_LENGTH('dbo.ALUNOS', 'ALURESPCPF') IS NULL
    ALTER TABLE [dbo].[ALUNOS] ADD [ALURESPCPF] [char](11) NULL
GO
IF COL_LENGTH('dbo.ALUNOS', 'ALUCIENCIAPRIVACIDADE') IS NULL
    ALTER TABLE [dbo].[ALUNOS] ADD [ALUCIENCIAPRIVACIDADE] [datetime] NULL
GO

-- Limpeza: tentativas com mais de 1 dia nao servem mais para nada
DELETE FROM [dbo].[LOGIN_TENTATIVAS] WHERE [DATAHORA] < DATEADD(DAY, -1, GETDATE())
GO

-- PERMISSOES (rodar uma vez por instalacao, ajustando o nome do login do sistema):
-- o usuario usado pelo PHP deve apenas ler e gravar dados, nunca alterar a estrutura do banco.
--   ALTER ROLE db_owner DROP MEMBER gkt_app;
--   ALTER ROLE db_datareader ADD MEMBER gkt_app;
--   ALTER ROLE db_datawriter ADD MEMBER gkt_app;
--   DENY ALTER, REFERENCES ON SCHEMA::dbo TO gkt_app;   -- nao negar CONTROL: isso bloquearia ate o SELECT
--   DENY UPDATE, DELETE ON dbo.AUDITORIA TO gkt_app;   -- trilha de auditoria nao pode ser apagada pelo sistema
