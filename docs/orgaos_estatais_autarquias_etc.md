# Orgaos Estatais, Autarquias e Entidades Publicas

## Objetivo
Este arquivo passa a ser a fonte de verdade para importacao inicial de orgaos publicos no modulo `organs`.

## Escopo de classificacao
Cada registro deve trazer, quando disponivel:
- `name`: nome oficial do orgao/entidade (obrigatorio)
- `acronym`: sigla
- `cnpj`: CNPJ formatado (`00.000.000/0000-00`) ou vazio
- `organ_type`: classificacao institucional (`administracao_direta`, `autarquia`, `autarquia_especial`, `fundacao_publica`, `empresa_publica`, `sociedade_economia_mista`)
- `government_level`: esfera (`federal`, `estadual`, `municipal`, `distrital`)
- `government_branch`: poder (`executivo`, `legislativo`, `judiciario`, `autonomo`)
- `supervising_organ`: orgao supervisor/vinculador
- `source_name`: origem da informacao
- `source_url`: referencia publica
- `city`, `state`: localidade principal
- `notes`: observacoes

## Plano de importacao
1. Validar estrutura do bloco CSV deste arquivo.
2. Executar migration para ampliar schema de `organs` com campos institucionais.
3. Rodar importador em modo validacao (`--validate-only`) e corrigir inconsistencias.
4. Rodar importador em modo efetivo para gravar os registros.
5. Revisar amostra no modulo de Orgaos (lista e detalhe) e registrar resultado da carga.

## Dataset canonicamente importavel (CSV)
```csv
name,acronym,cnpj,organ_type,government_level,government_branch,supervising_organ,source_name,source_url,city,state,notes
Casa Civil da Presidencia da Republica,CCPR,,administracao_direta,federal,executivo,Presidencia da Republica,Levantamento inicial interno 2026-03-05,https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,Orgao de articulacao da Presidencia
Ministerio do Trabalho e Emprego,MTE,,administracao_direta,federal,executivo,Presidencia da Republica,Levantamento inicial interno 2026-03-05,https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,Orgao central de politica laboral
Ministerio da Gestao e da Inovacao em Servicos Publicos,MGI,,administracao_direta,federal,executivo,Presidencia da Republica,Levantamento inicial interno 2026-03-05,https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,Orgao central de gestao publica
Ministerio da Fazenda,MF,,administracao_direta,federal,executivo,Presidencia da Republica,Levantamento inicial interno 2026-03-05,https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
Ministerio da Saude,MS,,administracao_direta,federal,executivo,Presidencia da Republica,Levantamento inicial interno 2026-03-05,https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
Ministerio da Educacao,MEC,,administracao_direta,federal,executivo,Presidencia da Republica,Levantamento inicial interno 2026-03-05,https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
Ministerio da Justica e Seguranca Publica,MJSP,,administracao_direta,federal,executivo,Presidencia da Republica,Levantamento inicial interno 2026-03-05,https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
Ministerio dos Transportes,MT,,administracao_direta,federal,executivo,Presidencia da Republica,Levantamento inicial interno 2026-03-05,https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
Ministerio da Previdencia Social,MPS,,administracao_direta,federal,executivo,Presidencia da Republica,Levantamento inicial interno 2026-03-05,https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
Ministerio da Integracao e do Desenvolvimento Regional,MIDR,,administracao_direta,federal,executivo,Presidencia da Republica,Levantamento inicial interno 2026-03-05,https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
Instituto Nacional do Seguro Social,INSS,,autarquia,federal,executivo,Ministerio da Previdencia Social,Levantamento inicial interno 2026-03-05,https://www.gov.br/inss,Brasilia,DF,
Instituto Nacional de Colonizacao e Reforma Agraria,INCRA,,autarquia,federal,executivo,Ministerio do Desenvolvimento Agrario e Agricultura Familiar,Levantamento inicial interno 2026-03-05,https://www.gov.br/incra,Brasilia,DF,
Instituto Brasileiro do Meio Ambiente e dos Recursos Naturais Renovaveis,IBAMA,,autarquia,federal,executivo,Ministerio do Meio Ambiente e Mudanca do Clima,Levantamento inicial interno 2026-03-05,https://www.gov.br/ibama,Brasilia,DF,
Instituto Chico Mendes de Conservacao da Biodiversidade,ICMBio,,autarquia,federal,executivo,Ministerio do Meio Ambiente e Mudanca do Clima,Levantamento inicial interno 2026-03-05,https://www.gov.br/icmbio,Brasilia,DF,
Agencia Nacional de Vigilancia Sanitaria,ANVISA,,autarquia_especial,federal,executivo,Ministerio da Saude,Levantamento inicial interno 2026-03-05,https://www.gov.br/anvisa,Brasilia,DF,
Agencia Nacional de Energia Eletrica,ANEEL,,autarquia_especial,federal,executivo,Ministerio de Minas e Energia,Levantamento inicial interno 2026-03-05,https://www.gov.br/aneel,Brasilia,DF,
Agencia Nacional de Telecomunicacoes,ANATEL,,autarquia_especial,federal,executivo,Ministerio das Comunicacoes,Levantamento inicial interno 2026-03-05,https://www.gov.br/anatel,Brasilia,DF,
Agencia Nacional de Transportes Terrestres,ANTT,,autarquia_especial,federal,executivo,Ministerio dos Transportes,Levantamento inicial interno 2026-03-05,https://www.gov.br/antt,Brasilia,DF,
Agencia Nacional de Transportes Aquaviarios,ANTAQ,,autarquia_especial,federal,executivo,Ministerio de Portos e Aeroportos,Levantamento inicial interno 2026-03-05,https://www.gov.br/antaq,Brasilia,DF,
Agencia Nacional do Petroleo Gas Natural e Biocombustiveis,ANP,,autarquia_especial,federal,executivo,Ministerio de Minas e Energia,Levantamento inicial interno 2026-03-05,https://www.gov.br/anp,Brasilia,DF,
Agencia Nacional de Aviacao Civil,ANAC,,autarquia_especial,federal,executivo,Ministerio de Portos e Aeroportos,Levantamento inicial interno 2026-03-05,https://www.gov.br/anac,Brasilia,DF,
Agencia Nacional de Aguas e Saneamento Basico,ANA,,autarquia_especial,federal,executivo,Ministerio da Integracao e do Desenvolvimento Regional,Levantamento inicial interno 2026-03-05,https://www.gov.br/ana,Brasilia,DF,
Agencia Nacional de Mineracao,ANM,,autarquia_especial,federal,executivo,Ministerio de Minas e Energia,Levantamento inicial interno 2026-03-05,https://www.gov.br/anm,Brasilia,DF,
Banco Central do Brasil,BCB,,autarquia,federal,autonomo,Conselho Monetario Nacional,Levantamento inicial interno 2026-03-05,https://www.bcb.gov.br,Brasilia,DF,
Comissao de Valores Mobiliarios,CVM,,autarquia,federal,autonomo,Ministerio da Fazenda,Levantamento inicial interno 2026-03-05,https://www.gov.br/cvm,Brasilia,DF,
Conselho Administrativo de Defesa Economica,CADE,,autarquia,federal,executivo,Ministerio da Justica e Seguranca Publica,Levantamento inicial interno 2026-03-05,https://www.gov.br/cade,Brasilia,DF,
Instituto Nacional de Metrologia Qualidade e Tecnologia,INMETRO,,autarquia,federal,executivo,Ministerio do Desenvolvimento Industria Comercio e Servicos,Levantamento inicial interno 2026-03-05,https://www.gov.br/inmetro,Duque de Caxias,RJ,
Departamento Nacional de Infraestrutura de Transportes,DNIT,,autarquia,federal,executivo,Ministerio dos Transportes,Levantamento inicial interno 2026-03-05,https://www.gov.br/dnit,Brasilia,DF,
Departamento Nacional de Obras Contra as Secas,DNOCS,,autarquia,federal,executivo,Ministerio da Integracao e do Desenvolvimento Regional,Levantamento inicial interno 2026-03-05,https://www.gov.br/dnocs,Fortaleza,CE,
Instituto Brasileiro de Geografia e Estatistica,IBGE,,fundacao_publica,federal,executivo,Ministerio do Planejamento e Orcamento,Levantamento inicial interno 2026-03-05,https://www.ibge.gov.br,Rio de Janeiro,RJ,
Fundacao Oswaldo Cruz,FIOCRUZ,,fundacao_publica,federal,executivo,Ministerio da Saude,Levantamento inicial interno 2026-03-05,https://portal.fiocruz.br,Rio de Janeiro,RJ,
Empresa Brasileira de Correios e Telegrafos,ECT,34.028.316/0001-03,empresa_publica,federal,executivo,Ministerio das Comunicacoes,Levantamento inicial interno 2026-03-05,https://www.correios.com.br,Brasilia,DF,
Empresa Brasileira de Servicos Hospitalares,EBSERH,15.126.437/0001-43,empresa_publica,federal,executivo,Ministerio da Educacao,Levantamento inicial interno 2026-03-05,https://www.gov.br/ebserh,Brasilia,DF,
Caixa Economica Federal,CAIXA,00.360.305/0001-04,empresa_publica,federal,executivo,Ministerio da Fazenda,Levantamento inicial interno 2026-03-05,https://www.caixa.gov.br,Brasilia,DF,
Banco Nacional de Desenvolvimento Economico e Social,BNDES,33.657.248/0001-89,empresa_publica,federal,executivo,Ministerio do Desenvolvimento Industria Comercio e Servicos,Levantamento inicial interno 2026-03-05,https://www.bndes.gov.br,Rio de Janeiro,RJ,
Servico Federal de Processamento de Dados,SERPRO,33.683.111/0001-07,empresa_publica,federal,executivo,Ministerio da Fazenda,Levantamento inicial interno 2026-03-05,https://www.serpro.gov.br,Brasilia,DF,
Empresa de Tecnologia e Informacoes da Previdencia,DATAPREV,42.422.253/0001-01,empresa_publica,federal,executivo,Ministerio da Previdencia Social,Levantamento inicial interno 2026-03-05,https://www.dataprev.gov.br,Brasilia,DF,
Banco do Brasil S.A.,BB,00.000.000/0001-91,sociedade_economia_mista,federal,executivo,Ministerio da Fazenda,Levantamento inicial interno 2026-03-05,https://www.bb.com.br,Brasilia,DF,
Petroleo Brasileiro S.A.,PETROBRAS,33.000.167/0001-01,sociedade_economia_mista,federal,executivo,Ministerio de Minas e Energia,Levantamento inicial interno 2026-03-05,https://petrobras.com.br,Rio de Janeiro,RJ,
Secretaria de Estado de Fazenda de Minas Gerais,SEF-MG,,administracao_direta,estadual,executivo,Governo do Estado de Minas Gerais,Levantamento inicial interno 2026-03-05,https://www.fazenda.mg.gov.br,Belo Horizonte,MG,
Secretaria da Fazenda e Planejamento do Estado de Sao Paulo,SEFAZ-SP,,administracao_direta,estadual,executivo,Governo do Estado de Sao Paulo,Levantamento inicial interno 2026-03-05,https://portal.fazenda.sp.gov.br,Sao Paulo,SP,
Secretaria Municipal da Fazenda de Sao Paulo,SF-SP,,administracao_direta,municipal,executivo,Prefeitura de Sao Paulo,Levantamento inicial interno 2026-03-05,https://www.prefeitura.sp.gov.br/cidade/secretarias/fazenda,Sao Paulo,SP,
```
