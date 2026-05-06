# Inventário de Bases dBASE III

**Gerado em:** 2026-04-22

## Contexto

Foi feita uma triagem dos arquivos em:

`/Users/cassiomachado/Documents/Clientes/Ortodontia Vogel/legacy-system/src/data/legacy-data`

Objetivo:

- separar os arquivos que **não** estão em `dBASE III (0x03)`
- manter em `legacy-data/` apenas os arquivos `DBF` com cabeçalho `0x03`

Destino dos arquivos movidos:

`/Users/cassiomachado/Documents/Clientes/Ortodontia Vogel/legacy-system/src/data/legacy-data/DBASE-IV`

## Arquivos movidos

Os arquivos abaixo foram removidos de `legacy-data/` porque não estavam em `0x03`:

| Arquivo | Cabeçalho detectado |
|---|---|
| `RELAT_orto07.DBF` | `0xEF` |
| `RELAT_orto08.DBF` | `0x04` |
| `RELAT_orto10.DBF` | `0x04` |
| `RELAT_orto23.DBF` | `0x04` |
| `RELAT_orto28.DBF` | `0x04` |
| `RELAT_orto29.DBF` | `0x04` |
| `RELAT_orto31.DBF` | `0x04` |
| `RELAT_orto32.DBF` | `0x04` |
| `RELAT_orto34.DBF` | `0x04` |
| `RELAT_orto35.DBF` | `0x04` |
| `RELAT_orto36.DBF` | `0x04` |
| `RELAT_orto37.DBF` | `0x04` |
| `RELAT_orto40.DBF` | `0x04` |
| `RELAT_orto41.DBF` | `0x04` |
| `RELAT_orto42.DBF` | `0x04` |
| `RELAT_orto43.DBF` | `0x04` |
| `RELAT_orto44.DBF` | `0x04` |
| `RELAT_orto49.DBF` | `invalid` |
| `RELAT_orto50.DBF` | `invalid` |
| `RELAT_orto51.DBF` | `invalid` |
| `RELAT_orto58.DBF` | `0x04` |
| `RELAT_orto60.DBF` | `0x04` |
| `RELAT_orto61.DBF` | `0x04` |
| `RELAT_orto63.DBF` | `0x04` |
| `RELAT_orto64.DBF` | `0x04` |
| `RELAT_orto65.DBF` | `0x04` |
| `RELAT_orto66.DBF` | `0x04` |
| `RELAT_orto69.DBF` | `0x04` |
| `RELAT_orto70.DBF` | `0x04` |
| `RELAT_orto71.DBF` | `0x04` |
| `RELAT_orto72.DBF` | `0x04` |
| `RELAT_orto73.DBF` | `0x04` |

## Arquivos restantes em `legacy-data/`

Os arquivos abaixo permaneceram em `legacy-data/` por estarem em `dBASE III (0x03)`:

| Arquivo | Cabeçalho | last_update | Registros | Duplicados |
|---|---|---|---:|---:|
| `RELAT_orto01.DBF` | `dBASE III (0x03)` | `2019-07-25` | 5799 | 8 |
| `RELAT_orto02.DBF` | `dBASE III (0x03)` | `2019-07-25` | 5799 | 8 |
| `RELAT_orto03.DBF` | `dBASE III (0x03)` | `2022-04-07` | 6161 | 28 |
| `RELAT_orto04.DBF` | `dBASE III (0x03)` | `2019-08-06` | 5894 | 40 |
| `RELAT_orto05.DBF` | `dBASE III (0x03)` | `2019-08-01` | 5850 | 17 |
| `RELAT_orto06.DBF` | `dBASE III (0x03)` | `2022-04-05` | 8277 | 2302 |
| `RELAT_orto09.DBF` | `dBASE III (0x03)` | `2019-03-20` | 5799 | 8 |
| `RELAT_orto11.DBF` | `dBASE III (0x03)` | `2019-08-06` | 5894 | 40 |
| `RELAT_orto12.DBF` | `dBASE III (0x03)` | `2019-07-25` | 5799 | 8 |
| `RELAT_orto13.DBF` | `dBASE III (0x03)` | `1919-05-30` | 5802 | 9 |
| `RELAT_orto14.DBF` | `dBASE III (0x03)` | `1919-05-30` | 5802 | 9 |
| `RELAT_orto15.DBF` | `dBASE III (0x03)` | `2019-08-01` | 5850 | 17 |
| `RELAT_orto16.DBF` | `dBASE III (0x03)` | `2019-08-01` | 5850 | 17 |
| `RELAT_orto17.DBF` | `dBASE III (0x03)` | `2022-04-07` | 6161 | 28 |
| `RELAT_orto18.DBF` | `dBASE III (0x03)` | `2021-04-06` | 6152 | 28 |
| `RELAT_orto19.DBF` | `dBASE III (0x03)` | `2019-08-01` | 5850 | 17 |
| `RELAT_orto20.DBF` | `dBASE III (0x03)` | `2019-08-01` | 5850 | 17 |
| `RELAT_orto21.DBF` | `dBASE III (0x03)` | `2019-08-01` | 5850 | 17 |
| `RELAT_orto22.DBF` | `dBASE III (0x03)` | `2026-03-09` | 6669 | 112 |
| `RELAT_orto24.DBF` | `dBASE III (0x03)` | `2026-03-09` | 6669 | 112 |
| `RELAT_orto25.DBF` | `dBASE III (0x03)` | `2022-04-05` | 8277 | 2302 |
| `RELAT_orto26.DBF` | `dBASE III (0x03)` | `2022-04-07` | 6161 | 28 |
| `RELAT_orto27.DBF` | `dBASE III (0x03)` | `2026-03-09` | 6669 | 112 |
| `RELAT_orto30.DBF` | `dBASE III (0x03)` | `2026-03-09` | 6669 | 112 |
| `RELAT_orto33.DBF` | `dBASE III (0x03)` | `2026-03-09` | 6723 | 124 |
| `RELAT_orto38.DBF` | `dBASE III (0x03)` | `2022-04-07` | 6161 | 28 |
| `RELAT_orto39.DBF` | `dBASE III (0x03)` | `2026-03-09` | 6669 | 112 |
| `RELAT_orto45.DBF` | `dBASE III (0x03)` | `2019-05-17` | 0 | 0 |
| `RELAT_orto46.DBF` | `dBASE III (0x03)` | `2019-07-25` | 4609 | 6 |
| `RELAT_orto47.DBF` | `dBASE III (0x03)` | `2019-07-25` | 4609 | 6 |
| `RELAT_orto48.DBF` | `dBASE III (0x03)` | `2019-07-25` | 4609 | 6 |
| `RELAT_orto52.DBF` | `dBASE III (0x03)` | `2019-08-01` | 0 | 0 |
| `RELAT_orto53.DBF` | `dBASE III (0x03)` | `2019-08-01` | 0 | 0 |
| `RELAT_orto54.DBF` | `dBASE III (0x03)` | `2019-08-01` | 0 | 0 |
| `RELAT_orto55.DBF` | `dBASE III (0x03)` | `2019-08-01` | 0 | 0 |
| `RELAT_orto56.DBF` | `dBASE III (0x03)` | `2026-03-09` | 6669 | 112 |
| `RELAT_orto57.DBF` | `dBASE III (0x03)` | `2026-03-09` | 6669 | 112 |
| `RELAT_orto59.DBF` | `dBASE III (0x03)` | `2026-03-09` | 6669 | 112 |
| `RELAT_orto62.DBF` | `dBASE III (0x03)` | `2026-03-09` | 6723 | 124 |
| `RELAT_orto67.DBF` | `dBASE III (0x03)` | `2022-04-07` | 6161 | 28 |
| `RELAT_orto68.DBF` | `dBASE III (0x03)` | `2026-03-09` | 6669 | 112 |
| `RELAT_orto74.DBF` | `dBASE III (0x03)` | `2026-03-09` | 6669 | 112 |

## Observações

- A contagem de duplicados foi calculada por nome normalizado, seguindo a mesma heurística usada na auditoria legada do projeto.
- A coluna `last_update` foi lida do cabeçalho interno do DBF. Ela ajuda a ordenar recência, mas pode estar inconsistente em alguns arquivos antigos.
- Os arquivos `RELAT_orto49.DBF`, `RELAT_orto50.DBF` e `RELAT_orto51.DBF` foram tratados como inválidos e movidos junto com os `0x04`.
- Este documento registra o estado após a limpeza do diretório `legacy-data/` para concentrar apenas arquivos `dBASE III`.
