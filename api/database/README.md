# Database artifacts

Esta pasta concentra todas as versões históricas do `.DBF` que alimentam o sistema (cada importação atualiza `uploads/RELAT_orto.DBF` e pode gerar novas cópias aqui para auditoria). Quando precisar inspecionar um dump antigo ou restaurar um backup, procure nesta pasta os arquivos `RELAT_orto*.DBF` (inclusive os nomes com timestamps) para reprocessar ou comparar registros.

## Recomendações

- **Permissões**: mantenha `database/` gravável pelo usuário que roda o PHP para que `downloadDBF.php` possa escrever novos arquivos antes de servir o download. Um `chmod u+w database` costuma bastar.
- **Consistência**: o script `src/downloadDBF.php` já sabe escrever aqui; não mova manualmente os arquivos que o banco ainda precisa referenciar. Se precisar limpar arquivos antigos, copie-os para outro lugar antes de deletar.

## Verificação automática

Antes de subir o servidor (ou rodar testes), execute `automation/check-dirs.sh` para garantir que `uploads/` e `database/` existem, têm permissão de escrita e possuem os arquivos esperados. O script também aponta qual diretório está sendo usado pelo backend (útil em deploys automatizados).
