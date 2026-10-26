# MySQL9.7.4 LTS — qualificacao e fronteira produtiva

Em26/10/2026, a auditoria read-only confirmou que o banco configurado no documentroot HG de reembolso.thadio.com permanecePercona5.7.44-48. Nao e recurso descartavel. O servidor compartilhado nao e administrado por este repositorio; nao trocar um tag nem apagar seu schema como se isso convertesse os dados.

## Entregue

Alvo canônico9.7.4 LTS no digest `mysql:9.7.4@sha256:8fb11c38b3551e22d50adbb688f027f8ff4ea2d2445aa277c1cc97b4af85ec54`. Em banco novo descartavel, o migrador real executou60migrations; repeticao pulou60 sem erro. Foi necessário fixar `collation_connection=utf8mb4_unicode_ci` somente na sessao do migrador: variaveis SQL da058 herdavam general_ci e conflitam com as colunas unicode_ci. Arquivos SQL publicados nao foram alterados; tampouco a conexao normal da aplicacao. O ensaio anterior falhou1267; a correcao passou no mesmo motor. Prova em `mysql974-qualification.safe.json`.

Reproduzir, com Dockerlocal e as duas imagens revisadas ja disponiveis, sem pull automatico:

```bash
python3 scripts/qualify_mysql974.py --run-synthetic --output /private/tmp/reembolso-mysql974-novo-run
```

O runner copia apenas fonte Git para diretorio privado, descarta qualquer.env do arquivo e gera uma.env sintética; nunca executa sobre a.env existente. A senha aleatoria nao vai para argv. PHP compartilha somente a rede do banco proprio; DBnetworknone, datatmpfs, sem volume de dados persistente. Nao executa seed, cria pessoa ou importa dados produtivos. Limpeza confere owner e remove apenas os recursos do ensaio.

## Pendente para substituir a base real

Qualificar destino privado9.7.4, TLS/rede entre aplicacao e banco sem expor3306 publicamente; inventariar consumidores/jobs/uploads; backup cifrado e restauravel do alvo real; conversao sequencial5.7→8.0→8.4→9.7.4 em datadirs distintos, com checker e paridade; janela com writer/scheduler fence; corte de configuracao, verificacaoPDO9.7.4, smoke e ciclos naturais; rollback e retirada do legado somente depois da prova. O ensaio de schema vazio nao prova conversao dos dados nem deploy. A auditoria central Woodpecker acompanha esta pendencia; nada foi instalado no VPS para este aplicativo.
