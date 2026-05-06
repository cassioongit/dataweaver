# Auditoria de Encoding do DBF

Data da varredura: 2026-04-15

## Resumo

Na varredura atual do `api/database/RELAT_orto.DBF`, não foi encontrado nenhum registro de dados com problema de acentuação detectável pelos validadores atuais.

Isso significa que a camada de leitura/escrita já está normalizando o texto visível para UTF-8 sem alterar a estrutura do DBF.

## Itens com problema detectável

Nenhum item de dados foi marcado na análise atual.

## Padrões de correção conhecidos

Os padrões abaixo devem ser corrigidos automaticamente quando aparecerem em arquivos futuros ou em linhas ainda não normalizadas.

| Atual (com erro) | Correto |
|---|---|
| V�nia N.Alcantara | Vânia N. Alcantara |
| S�o Paulo, SP | São Paulo, SP |
| Jo�o | João |
| Maranh�o | Maranhão |
| Cear� | Ceará |
| Par� | Pará |
| Goi�nia | Goiânia |
| Bras�lia | Brasília |
| Fran�a | França |
| Portugu�s | Português |
| Informa��o | Informação |
| Opera��o | Operação |
| Administra��o | Administração |
| Educa��o | Educação |
| Munic�pio | Município |
| Regi�o | Região |
| P�blico | Público |
| Sa�de | Saúde |

## O que podemos fazer para corrigir

1. Normalizar a leitura do texto legado para UTF-8.
2. Corrigir mojibake conhecido em nomes, cidades e responsáveis.
3. Ajustar espaços quebrados em nomes com iniciais, por exemplo `N.Alcantara` para `N. Alcantara`.
4. Registrar no preview e no upload quais campos foram alterados.
5. Manter o cabeçalho do DBF intacto.

## Duplicados após correção

Depois da normalização, alguns registros podem se tornar equivalentes e aparecer como duplicados.

Esse é um segundo momento do fluxo:

1. primeiro corrigimos acentuação e encoding;
2. depois reprocessamos a base para identificar duplicidades reais;
3. por fim, definimos a regra de consolidação.

## Observação sobre o cabeçalho

Os nomes dos campos do DBF são legados e não devem ser alterados.
Mesmo que o cabeçalho mostre caracteres quebrados em alguns nomes técnicos de campo, a estrutura precisa permanecer exatamente como está para compatibilidade com o sistema destino.
