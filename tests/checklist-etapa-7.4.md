# Checklist de Testes - Etapa 7.4 (Revisao de erros recorrentes)

## Pre-condicoes
- [ ] Existe `storage/logs/app.log` com permissao de leitura
- [ ] Script executavel: `chmod +x scripts/error-review.php` (se necessario)

## Execucao basica
- [ ] `./scripts/error-review.php` executa sem erro
- [ ] Saida em tabela exibe janela, totais e top de recorrencias
- [ ] Quando nao houver recorrencia, mensagem de "nenhum grupo recorrente" aparece

## Filtros e formatos
- [ ] `./scripts/error-review.php --window-hours 48` aplica janela de 48h
- [ ] `./scripts/error-review.php --levels ERROR` filtra apenas nivel ERROR
- [ ] `./scripts/error-review.php --output json` retorna JSON valido
- [ ] `./scripts/error-review.php --top 5` limita volume do ranking

## Relatorio persistido
- [ ] `./scripts/error-review.php --report-file storage/ops/error-review.md` gera arquivo markdown
- [ ] Arquivo markdown contem secao de totais e top recorrencias

## Gatilho de falha (automacao)
- [ ] `--fail-threshold` retorna codigo `2` quando `recurring_error_entries` atinge o limite
- [ ] `--fail-threshold` nao altera codigo de saida quando abaixo do limite
