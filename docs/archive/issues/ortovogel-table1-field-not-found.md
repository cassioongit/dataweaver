# ISSUE ABERTA: Erro `Table1: Field 'N°CAD' not found` no OrtoVogel

**Data do registro:** 13/04/2026  
**Status:** Aguardando teste do cliente

## Contexto

Esta nota documentou a investigação do erro `Table1: Field 'N°CAD' not found` no OrtoVogel. Ela foi arquivada porque a investigação pertence ao histórico de suporte, não à documentação ativa do produto.

## Investigação resumida

- O campo `NCAD` no DBF não foi alterado pela sanitização
- O erro parecia apontar para um arquivo auxiliar diferente do `RELAT_orto.DBF`
- A hipótese principal era problema de instalação ou de arquivo auxiliar corrompido
- O arquivo `RELAT_orto.DBF - Atual PROD` seguia como referência de produção conhecida

## Próximo passo histórico

Se esse problema voltar a aparecer, a nota original pode ser recuperada para reabrir a investigação.
