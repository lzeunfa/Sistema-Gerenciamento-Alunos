-- Ajusta a tabela ADMINISTRACAO para guardar senhas criptografadas (password_hash).
-- O hash tem 60+ caracteres, e senhas não podem ser únicas entre administradores.
-- Pode ser executado mais de uma vez sem problemas.
USE [gktgym]
GO
DECLARE @uq sysname;
SELECT @uq = kc.name
FROM sys.key_constraints kc
JOIN sys.index_columns ic ON ic.object_id = kc.parent_object_id AND ic.index_id = kc.unique_index_id
JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
WHERE kc.parent_object_id = OBJECT_ID('dbo.ADMINISTRACAO') AND kc.type = 'UQ' AND c.name = 'ADMSENHA';

IF @uq IS NOT NULL
    EXEC('ALTER TABLE [dbo].[ADMINISTRACAO] DROP CONSTRAINT [' + @uq + ']');
GO
ALTER TABLE [dbo].[ADMINISTRACAO] ALTER COLUMN [ADMSENHA] [varchar](255) NOT NULL
GO
