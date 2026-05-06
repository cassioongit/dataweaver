# Auditoria de Duplicidade do DBF

Data da análise: 2026-04-15

## Resumo

A base atual contém duplicidades reais de registros.

- Grupos de nomes repetidos exatamente: `65`
- Registros extras além da primeira ocorrência: `118`
- Após normalização de acentos e espaços, os grupos sobem para `66`
- Após normalização, os registros extras sobem para `119`

## Exemplos encontrados

| Nome | Ocorrências | Observação |
|---|---:|---|
| Taíssa de Castro Von Schilgen | 8 | Repetição clara de paciente |
| Vinícius Rodrigues Silva | 4 | Repetição clara de paciente |
| Quenia Corrêa Costa | 3 | Repetição clara de paciente |
| Bruno Melnik | 2 | Repetição clara de paciente |
| Aline Granado Bertin | 2 | Repetição clara de paciente |
| Relatório emitido | 3 | Linha de relatório, não paciente |
| Data de emissão: | 3 | Linha de relatório, não paciente |

## Leitura prática

As duplicidades não significam necessariamente erro no arquivo atual. Parte delas já existe na base histórica e pode ter sido acumulada por importações anteriores.

## Ordem recomendada

1. Corrigir encoding e acentuação.
2. Reprocessar a importação com os textos normalizados.
3. Reavaliar duplicidades depois da normalização.
4. Só então definir a regra de consolidação dos registros repetidos.

## Regra de cuidado

Não devemos tentar deduplicar antes da correção de texto, porque registros visualmente diferentes podem virar iguais depois da normalização.

## Próximo passo sugerido

Gerar uma lista consolidada de duplicidades reais, separando:

- pacientes válidos
- linhas técnicas ou de relatório
- repetições derivadas de importações antigas
