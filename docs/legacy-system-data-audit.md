# Auditoria (read-only) de dados do legado

**Gerado em:** 2026-04-16 20:12:25

Este relatório é **somente leitura**: não altera cabeçalho, estrutura, nem conteúdo de nenhum arquivo de entrada.

## Contexto e critérios

- Fonte: `legacy-system/src/data/` (recursivo).
- Tipos analisados: `DBF`, `CSV`, `XLS`, `XLSX`.
- Duplicados: por **nome normalizado** (trim → lower → translit ASCII → colapsar espaços → truncar 38).
- Inválidos: contagem por registro/linha afetada quando houver **bytes estranhos** (NULL/controles/CP1252 indefinido) e/ou **issues fortes** de encoding (UTF-8 inválido, `�`, mojibake reparado, reencode).
- Inventário complementar: veja [legacy-system-dbase3-inventory.md](/Users/cassiomachado/Documents/Development/dataweaver/docs/legacy-system-dbase3-inventory.md) para a triagem posterior que separou os arquivos não-`0x03` em `DBASE-IV` e registrou o que permaneceu em `legacy-data/`.

### Variantes de schema DBF detectadas (heurístico)

- v=0x04 fields=13 record_size=268 → 28 arquivo(s)
- v=0x03 fields=15 record_size=300 → 21 arquivo(s)
- v=0x03 fields=14 record_size=300 → 15 arquivo(s)
- v=0x03 fields=13 record_size=280 → 3 arquivo(s)
- v=0x03 fields=14 record_size=280 → 3 arquivo(s)

## 1) Arquivos DBF

| Nome do arquivo | Quantidade de registros | Tipo de cabeçalho | Registros duplicados | Registros com caracteres inválidos |
|---|---:|---|---:|---:|
| `legacy-data/RELAT_orto01.DBF` | 5799 | dBASE III (0x03) | 8 | 0 |
| `legacy-data/RELAT_orto02.DBF` | 5799 | dBASE III (0x03) | 8 | 0 |
| `legacy-data/RELAT_orto03.DBF` | 6161 | dBASE III (0x03) | 28 | 6099 |
| `legacy-data/RELAT_orto04.DBF` | 5894 | dBASE III (0x03) | 40 | 0 |
| `legacy-data/RELAT_orto05.DBF` | 5850 | dBASE III (0x03) | 17 | 0 |
| `legacy-data/RELAT_orto06.DBF` | 8277 | dBASE III (0x03) | 2302 | 0 |
| `legacy-data/RELAT_orto07.DBF` | — | — | — | — |
| `legacy-data/RELAT_orto08.DBF` | 6152 | dBASE IV (0x04) | 28 | 6099 |
| `legacy-data/RELAT_orto09.DBF` | 5799 | dBASE III (0x03) | 8 | 0 |
| `legacy-data/RELAT_orto10.DBF` | 6152 | dBASE IV (0x04) | 28 | 6099 |
| `legacy-data/RELAT_orto11.DBF` | 5894 | dBASE III (0x03) | 40 | 0 |
| `legacy-data/RELAT_orto12.DBF` | 5799 | dBASE III (0x03) | 8 | 0 |
| `legacy-data/RELAT_orto13.DBF` | 5802 | dBASE III (0x03) | 9 | 0 |
| `legacy-data/RELAT_orto14.DBF` | 5802 | dBASE III (0x03) | 9 | 0 |
| `legacy-data/RELAT_orto15.DBF` | 5850 | dBASE III (0x03) | 17 | 0 |
| `legacy-data/RELAT_orto16.DBF` | 5850 | dBASE III (0x03) | 17 | 0 |
| `legacy-data/RELAT_orto17.DBF` | 6161 | dBASE III (0x03) | 28 | 6099 |
| `legacy-data/RELAT_orto18.DBF` | 6152 | dBASE III (0x03) | 28 | 6099 |
| `legacy-data/RELAT_orto19.DBF` | 5850 | dBASE III (0x03) | 17 | 0 |
| `legacy-data/RELAT_orto20.DBF` | 5850 | dBASE III (0x03) | 17 | 0 |
| `legacy-data/RELAT_orto21.DBF` | 5850 | dBASE III (0x03) | 17 | 0 |
| `legacy-data/RELAT_orto22.DBF` | 6669 | dBASE III (0x03) | 112 | 6099 |
| `legacy-data/RELAT_orto23.DBF` | 6152 | dBASE IV (0x04) | 28 | 6099 |
| `legacy-data/RELAT_orto24.DBF` | 6669 | dBASE III (0x03) | 112 | 6099 |
| `legacy-data/RELAT_orto25.DBF` | 8277 | dBASE III (0x03) | 2302 | 0 |
| `legacy-data/RELAT_orto26.DBF` | 6161 | dBASE III (0x03) | 28 | 6099 |
| `legacy-data/RELAT_orto27.DBF` | 6669 | dBASE III (0x03) | 112 | 6099 |
| `legacy-data/RELAT_orto28.DBF` | 6152 | dBASE IV (0x04) | 28 | 6099 |
| `legacy-data/RELAT_orto29.DBF` | 6636 | dBASE IV (0x04) | 79 | 484 |
| `legacy-data/RELAT_orto30.DBF` | 6669 | dBASE III (0x03) | 112 | 6099 |
| `legacy-data/RELAT_orto31.DBF` | 6600 | dBASE IV (0x04) | 4 | 0 |
| `legacy-data/RELAT_orto32.DBF` | 6566 | dBASE IV (0x04) | 4 | 0 |
| `legacy-data/RELAT_orto33.DBF` | 6723 | dBASE III (0x03) | 112 | 6099 |
| `legacy-data/RELAT_orto34.DBF` | 6609 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto35.DBF` | 6566 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto36.DBF` | 6566 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto37.DBF` | 6566 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto38.DBF` | 6161 | dBASE III (0x03) | 28 | 6099 |
| `legacy-data/RELAT_orto39.DBF` | 6669 | dBASE III (0x03) | 112 | 6099 |
| `legacy-data/RELAT_orto40.DBF` | 6636 | dBASE IV (0x04) | 79 | 484 |
| `legacy-data/RELAT_orto41.DBF` | 6566 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto42.DBF` | 6566 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto43.DBF` | 6600 | dBASE IV (0x04) | 4 | 0 |
| `legacy-data/RELAT_orto44.DBF` | 6566 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto45.DBF` | 0 | dBASE III (0x03) | 0 | 0 |
| `legacy-data/RELAT_orto46.DBF` | 4609 | dBASE III (0x03) | 6 | 0 |
| `legacy-data/RELAT_orto47.DBF` | 4609 | dBASE III (0x03) | 6 | 0 |
| `legacy-data/RELAT_orto48.DBF` | 4609 | dBASE III (0x03) | 6 | 0 |
| `legacy-data/RELAT_orto49.DBF` | — | — | — | — |
| `legacy-data/RELAT_orto50.DBF` | — | — | — | — |
| `legacy-data/RELAT_orto51.DBF` | — | — | — | — |
| `legacy-data/RELAT_orto52.DBF` | 0 | dBASE III (0x03) | 0 | 0 |
| `legacy-data/RELAT_orto53.DBF` | 0 | dBASE III (0x03) | 0 | 0 |
| `legacy-data/RELAT_orto54.DBF` | 0 | dBASE III (0x03) | 0 | 0 |
| `legacy-data/RELAT_orto55.DBF` | 0 | dBASE III (0x03) | 0 | 0 |
| `legacy-data/RELAT_orto56.DBF` | 6669 | dBASE III (0x03) | 112 | 6099 |
| `legacy-data/RELAT_orto57.DBF` | 6669 | dBASE III (0x03) | 112 | 6099 |
| `legacy-data/RELAT_orto58.DBF` | 6636 | dBASE IV (0x04) | 79 | 484 |
| `legacy-data/RELAT_orto59.DBF` | 6669 | dBASE III (0x03) | 112 | 6099 |
| `legacy-data/RELAT_orto60.DBF` | 6600 | dBASE IV (0x04) | 4 | 0 |
| `legacy-data/RELAT_orto61.DBF` | 6566 | dBASE IV (0x04) | 4 | 0 |
| `legacy-data/RELAT_orto62.DBF` | 6723 | dBASE III (0x03) | 112 | 6099 |
| `legacy-data/RELAT_orto63.DBF` | 6609 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto64.DBF` | 6566 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto65.DBF` | 6566 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto66.DBF` | 6566 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto67.DBF` | 6161 | dBASE III (0x03) | 28 | 6099 |
| `legacy-data/RELAT_orto68.DBF` | 6669 | dBASE III (0x03) | 112 | 6099 |
| `legacy-data/RELAT_orto69.DBF` | 6636 | dBASE IV (0x04) | 79 | 484 |
| `legacy-data/RELAT_orto70.DBF` | 6566 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto71.DBF` | 6566 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto72.DBF` | 6600 | dBASE IV (0x04) | 4 | 0 |
| `legacy-data/RELAT_orto73.DBF` | 6566 | dBASE IV (0x04) | 1 | 0 |
| `legacy-data/RELAT_orto74.DBF` | 6669 | dBASE III (0x03) | 112 | 6099 |

## 2) Arquivos CSV, XLS e XLSX

| Nome do arquivo | Tipo do arquivo | Quantidade de registros | Registros duplicados | Estrutura de importação |
|---|---|---:|---:|---|
| `csv/Lista_de_pacientes01.csv` | CSV | 37 | 1 | Importação 2 |
| `csv/Lista_de_pacientes02.csv` | CSV | 18 | 0 | Importação 2 |
| `csv/Lista_de_pacientes03.csv` | CSV | 29 | 1 | Importação 2 |
| `csv/Lista_de_pacientes04.csv` | CSV | 0 | 0 | Importação 1 |
| `xls/Lista_de_pacientes01.xls` | XLS | 1 | 0 | Importação 6 |
| `xls/Lista_de_pacientes02.xls` | XLS | 16 | 0 | Importação 7 |
| `xls/Lista_de_pacientes03.xls` | XLS | 2162 | 5 | Importação 7 |
| `xls/Lista_de_pacientes04.xls` | XLS | 0 | 0 | Importação 5 |
| `xls/Lista_de_pacientes05.xls` | XLS | 2 | 1 | Importação 6 |
| `xls/Lista_de_pacientes06.xls` | XLS | 94 | 34 | Importação 6 |
| `xls/Lista_de_pacientes07.xls` | XLS | 0 | 0 | Importação 5 |
| `xls/Lista_de_pacientes08.xls` | XLS | 0 | 0 | Importação 5 |
| `xls/Lista_de_pacientes09.xls` | XLS | 0 | 0 | Importação 5 |
| `xls/Lista_de_pacientes10.xls` | XLS | 0 | 0 | Importação 5 |
| `xls/Lista_de_pacientes11.xls` | XLS | 6 | 2 | Importação 6 |
| `xls/Lista_de_pacientes12.xls` | XLS | 18 | 5 | Importação 6 |
| `xls/Lista_de_pacientes13.xls` | XLS | 18 | 5 | Importação 6 |
| `xls/Lista_de_pacientes14.xls` | XLS | 28 | 9 | Importação 6 |
| `xls/Lista_de_pacientes15.xls` | XLS | 45 | 15 | Importação 6 |
| `xls/Lista_de_pacientes16.xls` | XLS | 1 | 0 | Importação 6 |
| `xls/Lista_de_pacientes17.xls` | XLS | 0 | 0 | Importação 5 |
| `xls/Lista_de_pacientes18.xls` | XLS | 1 | 0 | Importação 6 |
| `xls/Lista_de_pacientes19.xls` | XLS | 0 | 0 | Importação 5 |
| `xls/Lista_de_pacientes20.xls` | XLS | 18 | 5 | Importação 6 |
| `xls/Lista_de_pacientes21.xls` | XLS | 2 | 1 | Importação 6 |
| `xlsx/Lista_de_pacientes01.xlsx` | XLSX | 0 | 0 | Importação 3 |
| `xlsx/Lista_de_pacientes02.xlsx` | XLSX | 12 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes03.xlsx` | XLSX | 4 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes04.xlsx` | XLSX | 7 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes05.xlsx` | XLSX | 2 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes06.xlsx` | XLSX | 5 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes07.xlsx` | XLSX | 3 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes08.xlsx` | XLSX | 1 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes09.xlsx` | XLSX | 3 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes10.xlsx` | XLSX | 9 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes11.xlsx` | XLSX | 3 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes12.xlsx` | XLSX | 9 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes13.xlsx` | XLSX | 9 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes14.xlsx` | XLSX | 18 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes15.xlsx` | XLSX | 24 | 1 | Importação 4 |
| `xlsx/Lista_de_pacientes16.xlsx` | XLSX | 14 | 1 | Importação 4 |
| `xlsx/Lista_de_pacientes17.xlsx` | XLSX | 13 | 1 | Importação 4 |
| `xlsx/Lista_de_pacientes18.xlsx` | XLSX | 10 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes19.xlsx` | XLSX | 7 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes20.xlsx` | XLSX | 5 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes21.xlsx` | XLSX | 1 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes22.xlsx` | XLSX | 8 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes23.xlsx` | XLSX | 15 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes24.xlsx` | XLSX | 9 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes25.xlsx` | XLSX | 13 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes26.xlsx` | XLSX | 11 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes27.xlsx` | XLSX | 1 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes28.xlsx` | XLSX | 6 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes29.xlsx` | XLSX | 7 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes30.xlsx` | XLSX | 16 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes31.xlsx` | XLSX | 13 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes32.xlsx` | XLSX | 17 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes33.xlsx` | XLSX | 10 | 0 | Importação 4 |
| `xlsx/Lista_de_pacientes34.xlsx` | XLSX | 0 | 0 | Importação 3 |
| `xlsx/Lista_de_pacientes35.xlsx` | XLSX | 0 | 0 | Importação 3 |
| `xlsx/Lista_de_pacientes36.xlsx` | XLSX | 0 | 0 | Importação 3 |

### Definições de importação

| Estrutura | Regra (tipo + cabeçalho + colunas) | Arquivos |
|---|---|---:|
| Importação 1 | `CSV\|CSV (header não reconhecido)\|1` | 1 |
| Importação 2 | `CSV\|CSV MedKey (header linha 1)\|28` | 3 |
| Importação 3 | `XLSX\|XLS/XLSX (dados a partir da linha 6)\|1` | 4 |
| Importação 4 | `XLSX\|XLS/XLSX (dados a partir da linha 6)\|8` | 32 |
| Importação 5 | `XLS\|XLS/XLSX (dados a partir da linha 6)\|1` | 7 |
| Importação 6 | `XLS\|XLS/XLSX (dados a partir da linha 6)\|11` | 12 |
| Importação 7 | `XLS\|XLS/XLSX (dados a partir da linha 6)\|8` | 2 |

## Arquivos com erro de leitura

| Tipo | Arquivo | Erro |
|---|---|---|
| DBF | `legacy-data/RELAT_orto07.DBF` | Não foi possível inferir o schema (descritores) de forma consistente com o record_size. |
| DBF | `legacy-data/RELAT_orto49.DBF` | Header inválido (menos de 32 bytes). |
| DBF | `legacy-data/RELAT_orto50.DBF` | Header inválido (menos de 32 bytes). |
| DBF | `legacy-data/RELAT_orto51.DBF` | Header inválido (menos de 32 bytes). |

## 3) Análise legado vs atual (gaps e riscos)

### Comparações diretas (arquivos)

- `legacy-system/src/upload.php` vs `api/src/upload.php` + `api/src/preview.php`
- `legacy-system/src/downloadDBF.php` vs `api/src/downloadDBF.php`
- `legacy-system/src/vendor/csvtodbf/CharSVtoDbf.php` vs `api/vendor/csvtodbf/CharSVtoDbf.php` + `api/src/utils/NativeDbf.php`

### Achados críticos (sem PII)

- **Regra de duplicidade mudou**: no legado, duplicado era basicamente `trim(nome)`; no atual, a chave é **normalizada** (casefold + remoção de acentos + colapso de espaços + truncagem 38). Isso pode transformar nomes antes “diferentes” em iguais (colisão) e gerar risco de **não inserção** ou **atualização inesperada**.
- **Validação de CSV mais rígida**: o atual exige cabeçalho MedKey (colunas específicas) e pode rejeitar exports fora do padrão.
- **Fluxo “preview antes de gravar”**: no legado o upload grava direto; no atual existe preview (dry-run) e escrita com cópia de trabalho, reduzindo risco de corrupção — mas qualquer divergência de schema/versão pode quebrar o fluxo.
- **DBF atual (referência do sistema) — metadados do cabeçalho**: `api/database/RELAT_orto.DBF` version=0x04, fields=13, record_size=268.
  - **Integridade suspeita**: file_size=1649429 < expected_min_size=1649430.
  - **EOF terminator inesperado**: last_byte=0x20 (esperado 0x1A).
- **Export/download**: o download atual gera um DBF “do zero” via `CharSVtoDBF` apontando para um arquivo temporário. Se o driver/geração não respeitar o formato esperado do sistema destino, isso pode gerar incompatibilidade.

### Recomendações de ação

- Rodar este relatório sempre que entrar uma nova pasta de dumps/exports antes de repetir testes de importação.
- Se houver colisões altas de duplicidade após normalização, definir uma regra explícita de desambiguação (ex.: usar CPF quando presente, ou exigir confirmação no preview).
