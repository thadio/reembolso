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
"Casa Civil da Presidencia da Republica",CCPR,00.394.411/0001-09,administracao_direta,federal,executivo,"Presidencia da Republica","Levantamento inicial interno 2026-03-05",https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,"Orgao de articulacao da Presidencia"
"Ministerio do Trabalho e Emprego",MTE,23.612.685/0001-22,administracao_direta,federal,executivo,"Presidencia da Republica","Levantamento inicial interno 2026-03-05",https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,"Orgao central de politica laboral"
"Ministerio da Gestao e da Inovacao em Servicos Publicos",MGI,00.489.828/0001-55,administracao_direta,federal,executivo,"Presidencia da Republica","Levantamento inicial interno 2026-03-05",https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,"Orgao central de gestao publica"
"Ministerio da Fazenda",MF,00.394.460/0001-41,administracao_direta,federal,executivo,"Presidencia da Republica","Levantamento inicial interno 2026-03-05",https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
"Ministerio da Saude",MS,00.394.544/0001-85,administracao_direta,federal,executivo,"Presidencia da Republica","Levantamento inicial interno 2026-03-05",https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
"Ministerio da Educacao",MEC,00.394.445/0001-01,administracao_direta,federal,executivo,"Presidencia da Republica","Levantamento inicial interno 2026-03-05",https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
"Ministerio da Justica e Seguranca Publica",MJSP,00.394.494/0001-36,administracao_direta,federal,executivo,"Presidencia da Republica","Levantamento inicial interno 2026-03-05",https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
"Ministerio dos Transportes",MT,37.115.342/0001-67,administracao_direta,federal,executivo,"Presidencia da Republica","Levantamento inicial interno 2026-03-05",https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
"Ministerio da Previdencia Social",MPS,00.394.528/0001-92,administracao_direta,federal,executivo,"Presidencia da Republica","Levantamento inicial interno 2026-03-05",https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
"Ministerio da Integracao e do Desenvolvimento Regional",MIDR,03.353.358/0001-96,administracao_direta,federal,executivo,"Presidencia da Republica","Levantamento inicial interno 2026-03-05",https://www.gov.br/pt-br/orgaos-do-governo,Brasilia,DF,
"Instituto Nacional do Seguro Social",INSS,29.979.036/0001-40,autarquia,federal,executivo,"Ministerio da Previdencia Social","Levantamento inicial interno 2026-03-05",https://www.gov.br/inss,Brasilia,DF,
"Instituto Nacional de Colonizacao e Reforma Agraria",INCRA,03.204.421/0001-22,autarquia,federal,executivo,"Ministerio do Desenvolvimento Agrario e Agricultura Familiar","Levantamento inicial interno 2026-03-05",https://www.gov.br/incra,Brasilia,DF,
"Instituto Brasileiro do Meio Ambiente e dos Recursos Naturais Renovaveis",IBAMA,03.659.166/0001-02,autarquia,federal,executivo,"Ministerio do Meio Ambiente e Mudanca do Clima","Levantamento inicial interno 2026-03-05",https://www.gov.br/ibama,Brasilia,DF,
"Instituto Chico Mendes de Conservacao da Biodiversidade",ICMBio,08.829.974/0001-94,autarquia,federal,executivo,"Ministerio do Meio Ambiente e Mudanca do Clima","Levantamento inicial interno 2026-03-05",https://www.gov.br/icmbio,Brasilia,DF,
"Agencia Nacional de Vigilancia Sanitaria",ANVISA,03.112.386/0001-11,autarquia_especial,federal,executivo,"Ministerio da Saude","Levantamento inicial interno 2026-03-05",https://www.gov.br/anvisa,Brasilia,DF,
"Agencia Nacional de Energia Eletrica",ANEEL,02.270.669/0001-29,autarquia_especial,federal,executivo,"Ministerio de Minas e Energia","Levantamento inicial interno 2026-03-05",https://www.gov.br/aneel,Brasilia,DF,
"Agencia Nacional de Telecomunicacoes",ANATEL,02.030.715/0001-12,autarquia_especial,federal,executivo,"Ministerio das Comunicacoes","Levantamento inicial interno 2026-03-05",https://www.gov.br/anatel,Brasilia,DF,
"Agencia Nacional de Transportes Terrestres",ANTT,04.898.488/0001-77,autarquia_especial,federal,executivo,"Ministerio dos Transportes","Levantamento inicial interno 2026-03-05",https://www.gov.br/antt,Brasilia,DF,
"Agencia Nacional de Transportes Aquaviarios",ANTAQ,04.903.587/0001-08,autarquia_especial,federal,executivo,"Ministerio de Portos e Aeroportos","Levantamento inicial interno 2026-03-05",https://www.gov.br/antaq,Brasilia,DF,
"Agencia Nacional do Petroleo Gas Natural e Biocombustiveis",ANP,02.313.673/0001-27,autarquia_especial,federal,executivo,"Ministerio de Minas e Energia","Levantamento inicial interno 2026-03-05",https://www.gov.br/anp,Brasilia,DF,
"Agencia Nacional de Aviacao Civil",ANAC,07.947.821/0001-89,autarquia_especial,federal,executivo,"Ministerio de Portos e Aeroportos","Levantamento inicial interno 2026-03-05",https://www.gov.br/anac,Brasilia,DF,
"Agencia Nacional de Aguas e Saneamento Basico",ANA,04.204.444/0001-08,autarquia_especial,federal,executivo,"Ministerio da Integracao e do Desenvolvimento Regional","Levantamento inicial interno 2026-03-05",https://www.gov.br/ana,Brasilia,DF,
"Agencia Nacional de Mineracao",ANM,29.406.625/0001-30,autarquia_especial,federal,executivo,"Ministerio de Minas e Energia","Levantamento inicial interno 2026-03-05",https://www.gov.br/anm,Brasilia,DF,
"Banco Central do Brasil",BCB,00.038.166/0001-05,autarquia,federal,autonomo,"Conselho Monetario Nacional","Levantamento inicial interno 2026-03-05",https://www.bcb.gov.br,Brasilia,DF,
"Comissao de Valores Mobiliarios",CVM,29.507.878/0001-08,autarquia,federal,autonomo,"Ministerio da Fazenda","Levantamento inicial interno 2026-03-05",https://www.gov.br/cvm,Brasilia,DF,
"Conselho Administrativo de Defesa Economica",CADE,00.418.993/0001-16,autarquia,federal,executivo,"Ministerio da Justica e Seguranca Publica","Levantamento inicial interno 2026-03-05",https://www.gov.br/cade,Brasilia,DF,
"Instituto Nacional de Metrologia Qualidade e Tecnologia",INMETRO,00.662.270/0001-68,autarquia,federal,executivo,"Ministerio do Desenvolvimento Industria Comercio e Servicos","Levantamento inicial interno 2026-03-05",https://www.gov.br/inmetro,"Duque de Caxias",RJ,
"Departamento Nacional de Infraestrutura de Transportes",DNIT,04.892.707/0001-00,autarquia,federal,executivo,"Ministerio dos Transportes","Levantamento inicial interno 2026-03-05",https://www.gov.br/dnit,Brasilia,DF,
"Departamento Nacional de Obras Contra as Secas",DNOCS,00.043.711/0001-43,autarquia,federal,executivo,"Ministerio da Integracao e do Desenvolvimento Regional","Levantamento inicial interno 2026-03-05",https://www.gov.br/dnocs,Fortaleza,CE,
"Instituto Brasileiro de Geografia e Estatistica",IBGE,33.787.094/0001-40,fundacao_publica,federal,executivo,"Ministerio do Planejamento e Orcamento","Levantamento inicial interno 2026-03-05",https://www.ibge.gov.br,"Rio de Janeiro",RJ,
"Fundacao Oswaldo Cruz",FIOCRUZ,33.781.055/0001-35,fundacao_publica,federal,executivo,"Ministerio da Saude","Levantamento inicial interno 2026-03-05",https://portal.fiocruz.br,"Rio de Janeiro",RJ,
"Secretaria de Estado de Fazenda de Minas Gerais",SEF-MG,18.715.615/0001-60,administracao_direta,estadual,executivo,"Governo do Estado de Minas Gerais","Levantamento inicial interno 2026-03-05",https://www.fazenda.mg.gov.br,"Belo Horizonte",MG,
"Secretaria da Fazenda e Planejamento do Estado de Sao Paulo",SEFAZ-SP,46.377.222/0001-29,administracao_direta,estadual,executivo,"Governo do Estado de Sao Paulo","Levantamento inicial interno 2026-03-05",https://portal.fazenda.sp.gov.br,"Sao Paulo",SP,
"Secretaria Municipal da Fazenda de Sao Paulo",SF-SP,46.395.000/0001-39,administracao_direta,municipal,executivo,"Prefeitura de Sao Paulo","Levantamento inicial interno 2026-03-05",https://www.prefeitura.sp.gov.br/cidade/secretarias/fazenda,"Sao Paulo",SP,
"Companhia de Saneamento do Acre",SANEACRE,04.033.608/0001-43,empresa_publica,estadual,executivo,"Governo do Estado do Acre","Cadastro complementar de estatais estaduais 2026-03-09",,,AC,
"Companhia de Saneamento de Alagoas",CASAL,12.294.708/0001-81,empresa_publica,estadual,executivo,"Governo de Alagoas","Cadastro complementar de estatais estaduais 2026-03-09",,,AL,
"Companhia de Agua e Esgoto do Amapa",CAESA,05.976.311/0001-04,empresa_publica,estadual,executivo,"Governo do Amapa","Cadastro complementar de estatais estaduais 2026-03-09",,,AP,
"Companhia de Saneamento do Amazonas",COSAMA,04.620.351/0001-07,empresa_publica,estadual,executivo,"Governo do Amazonas","Cadastro complementar de estatais estaduais 2026-03-09",,,AM,
"Empresa Baiana de Aguas e Saneamento",EMBASA,13.504.675/0001-10,empresa_publica,estadual,executivo,"Governo da Bahia","Cadastro complementar de estatais estaduais 2026-03-09",,,BA,
"Companhia de Agua e Esgoto do Ceara",CAGECE,07.040.108/0001-57,empresa_publica,estadual,executivo,"Governo do Ceara","Cadastro complementar de estatais estaduais 2026-03-09",,,CE,
"Companhia Espirito-Santense de Saneamento",CESAN,28.151.363/0001-47,empresa_publica,estadual,executivo,"Governo do Espirito Santo","Cadastro complementar de estatais estaduais 2026-03-09",,,ES,
"Saneamento de Goias",SANEAGO,01.616.929/0001-02,empresa_publica,estadual,executivo,"Governo de Goias","Cadastro complementar de estatais estaduais 2026-03-09",,,GO,
"Maranhao Parcerias",MAPA,21.062.565/0001-03,empresa_publica,estadual,executivo,"Governo do Maranhao","Cadastro complementar de estatais estaduais 2026-03-09",,,MA,
"Companhia Mato-grossense de Mineracao",METAMAT,03.987.971/0001-17,empresa_publica,estadual,executivo,"Governo de Mato Grosso","Cadastro complementar de estatais estaduais 2026-03-09",,,MT,
"Empresa de Saneamento de Mato Grosso do Sul",SANESUL,01.998.425/0001-95,empresa_publica,estadual,executivo,"Governo de MS","Cadastro complementar de estatais estaduais 2026-03-09",,,MS,
"Companhia de Saneamento de Minas Gerais",COPASA,17.281.106/0001-03,empresa_publica,estadual,executivo,"Governo de MG","Cadastro complementar de estatais estaduais 2026-03-09",,,MG,
"Companhia de Saneamento do Para",COSANPA,04.945.341/0001-90,empresa_publica,estadual,executivo,"Governo do Para","Cadastro complementar de estatais estaduais 2026-03-09",,,PA,
"Companhia de Agua e Esgotos da Paraiba",CAGEPA,09.123.654/0001-87,empresa_publica,estadual,executivo,"Governo da Paraiba","Cadastro complementar de estatais estaduais 2026-03-09",,,PB,
"Companhia Paranaense de Energia",COPEL,76.483.817/0001-20,empresa_publica,estadual,executivo,"Governo do Parana","Cadastro complementar de estatais estaduais 2026-03-09",,,PR,
"Companhia de Tecnologia da Informacao e Comunicacao do Parana",CELEPAR,76.545.011/0001-19,empresa_publica,estadual,executivo,"Governo do Parana","Cadastro complementar de estatais estaduais 2026-03-09",,,PR,
"Companhia Pernambucana de Saneamento",COMPESA,09.769.035/0001-64,empresa_publica,estadual,executivo,"Governo de Pernambuco","Cadastro complementar de estatais estaduais 2026-03-09",,,PE,
"Aguas e Esgotos do Piaui",AGESPISA,06.845.747/0001-27,empresa_publica,estadual,executivo,"Governo do Piaui","Cadastro complementar de estatais estaduais 2026-03-09",,,PI,
"Companhia Estadual de Aguas e Esgotos",CEDAE,33.352.394/0001-04,empresa_publica,estadual,executivo,"Governo do RJ","Cadastro complementar de estatais estaduais 2026-03-09",,,RJ,
"Companhia de Aguas e Esgotos do RN",CAERN,08.334.385/0001-35,empresa_publica,estadual,executivo,"Governo do RN","Cadastro complementar de estatais estaduais 2026-03-09",,,RN,
"Companhia Riograndense de Saneamento",CORSAN,92.802.784/0001-90,empresa_publica,estadual,executivo,"Governo do RS","Cadastro complementar de estatais estaduais 2026-03-09",,,RS,
"Companhia de Aguas e Esgotos de Rondonia",CAERD,05.914.893/0001-92,empresa_publica,estadual,executivo,"Governo de RO","Cadastro complementar de estatais estaduais 2026-03-09",,,RO,
"Companhia de Aguas e Esgotos de Roraima",CAER,05.939.467/0001-15,empresa_publica,estadual,executivo,"Governo de Roraima","Cadastro complementar de estatais estaduais 2026-03-09",,,RR,
"Companhia Catarinense de Aguas e Saneamento",CASAN,82.508.433/0001-17,empresa_publica,estadual,executivo,"Governo de SC","Cadastro complementar de estatais estaduais 2026-03-09",,,SC,
"Companhia de Saneamento Basico do Estado de SP",SABESP,43.776.517/0001-80,empresa_publica,estadual,executivo,"Governo de Sao Paulo","Cadastro complementar de estatais estaduais 2026-03-09",,,SP,
"Companhia de Saneamento de Sergipe",DESO,13.018.171/0001-90,empresa_publica,estadual,executivo,"Governo de Sergipe","Cadastro complementar de estatais estaduais 2026-03-09",,,SE,
"Agencia Tocantinense de Saneamento",ATS,08.991.325/0001-01,empresa_publica,estadual,executivo,"Governo do Tocantins","Cadastro complementar de estatais estaduais 2026-03-09",,,TO,
"AGENCIA BRASILEIRA GESTORA DE FUNDOS GARANTIDORES E GARANTIAS S.A -ABGF",,17.909.518/0001-45,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500005200 | DT_CADASTRO: 11/04/2013 | COD_NATUREZA_JURIDICA: 2011"
"AGENCIA ESPECIAL DE FINANCIAMENTO INDUSTRIAL FINAME",,33.660.564/0001-00,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 33300048774 | DT_CADASTRO: 25/04/1967 | COD_NATUREZA_JURIDICA: 2011"
"ALADA - EMPRESA DE PROJETOS AEROESPACIAIS DO BRASIL S.A",,61.993.931/0001-22,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500011676 | DT_CADASTRO: 30/07/2025 | COD_NATUREZA_JURIDICA: 2011"
"BANCO DO BRASIL S.A.",,00.000.000/0001-91,sociedade_economia_mista,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300000638 | DT_CADASTRO: 19/05/1961 | COD_NATUREZA_JURIDICA: 2038"
"BANCO NACIONAL DE DESENVOLVIMENTO ECONOMICO E SOCIAL - BNDES",,33.657.248/0001-89,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000372 | DT_CADASTRO: 30/09/1971 | COD_NATUREZA_JURIDICA: 2011"
"BANESTES SA BANCO DO ESTADO DO ESPIRITO SANTO",,28.127.603/0001-78,sociedade_economia_mista,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 32300000703 | DT_CADASTRO: 08/08/1966 | COD_NATUREZA_JURIDICA: 2038"
"BRB - BANCO DE BRASILIA",BRB,00.000.208/0001-00,sociedade_economia_mista,distrital,executivo,"Governo do Distrito Federal","Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,Brasilia,DF,"NIRE: 53300001430 | DT_CADASTRO: 18/08/1966 | COD_NATUREZA_JURIDICA: 2038"
"CAIXA ECONOMICA FEDERAL",,00.360.305/0001-04,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000381 | DT_CADASTRO: 03/02/1971 | COD_NATUREZA_JURIDICA: 2011"
"CASA DA MOEDA DO BRASIL C. M. B.",,34.164.319/0001-74,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000330 | DT_CADASTRO: 30/10/1973 | COD_NATUREZA_JURIDICA: 2011"
"CEB LAJEADO S.A - CEBLAJEADO",,03.677.638/0001-50,sociedade_economia_mista,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300006130 | DT_CADASTRO: 01/03/2000 | COD_NATUREZA_JURIDICA: 2038"
"CEB PARTICIPACOES S.A  CEBPAR",,03.682.014/0001-20,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300006148 | DT_CADASTRO: 02/03/2000 | COD_NATUREZA_JURIDICA: 2011"
"CENTRAIS DE ABASTECIMENTO DO DISTRITO FEDERAL - CEASA/DF",,00.314.310/0001-80,sociedade_economia_mista,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300001634 | DT_CADASTRO: 25/11/1971 | COD_NATUREZA_JURIDICA: 2038"
"COMPANHIA BRASILEIRA DE TRENS URBANOS",,42.357.483/0001-26,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500008756 | DT_CADASTRO: 24/03/2021 | COD_NATUREZA_JURIDICA: 2011"
"COMPANHIA DE DESENVOLVIMENTO DO VALE DO SAO FRANCISCO E DO PARNAIBA - CODEVASF",,00.399.857/0001-26,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000313 | DT_CADASTRO: 10/12/1974 | COD_NATUREZA_JURIDICA: 2011"
"COMPANHIA DE DESENVOLVIMENTO HABITACIONAL DO DISTRITO FEDERAL CODHAB/DF",,09.335.575/0001-30,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500003312 | DT_CADASTRO: 11/03/2008 | COD_NATUREZA_JURIDICA: 2011"
"COMPANHIA DE PESQUISA DE RECURSOS MINERAIS C. P. R. M.",,00.091.652/0001-89,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300001669 | DT_CADASTRO: 20/01/1970 | COD_NATUREZA_JURIDICA: 2011"
"COMPANHIA DE PLANEJAMENTO DO DISTRITO FEDERAL - CODEPLAN - EM LIQUIDACAO",,00.046.060/0001-45,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500005668 | DT_CADASTRO: 05/12/1966 | COD_NATUREZA_JURIDICA: 2011"
"COMPANHIA DE SANEAMENTO AMBIENTAL DO DISTRITO FEDERAL - CAESB",,00.082.024/0001-37,sociedade_economia_mista,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300001715 | DT_CADASTRO: 01/07/1969 | COD_NATUREZA_JURIDICA: 2038"
"COMPANHIA DO METROPOLITANO DO DISTRITO FEDERAL - METRO -DF",,38.070.074/0001-77,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000950 | DT_CADASTRO: 08/03/1994 | COD_NATUREZA_JURIDICA: 2011"
"COMPANHIA ENERGETICA DE BRASILIA CEB",,00.070.698/0001-11,sociedade_economia_mista,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300001545 | DT_CADASTRO: 09/01/1969 | COD_NATUREZA_JURIDICA: 2038"
"COMPANHIA IMOBILIARIA DE BRASILIA - TERRACAP",,00.359.877/0001-73,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000348 | DT_CADASTRO: 04/10/1973 | COD_NATUREZA_JURIDICA: 2011"
"COMPANHIA NACIONAL DE ABASTECIMENTO - CONAB",,26.461.699/0001-80,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000933 | DT_CADASTRO: 04/01/1991 | COD_NATUREZA_JURIDICA: 2011"
"COMPANHIA URBANIZADORA DA NOVA CAPITAL DO BRASIL - NOVACAP",,00.037.457/0001-70,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000909 | DT_CADASTRO: 13/07/1981 | COD_NATUREZA_JURIDICA: 2011"
"CONTRATO DE SOCIEDADE EM CONTA DE PARTICIPACAO",,,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500003215 | DT_CADASTRO: 19/12/2007 | COD_NATUREZA_JURIDICA: 2011"
"CONTRATO DE SOCIEDADE EM CONTA DE PARTICIPACAO",,,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500003231 | DT_CADASTRO: 19/12/2007 | COD_NATUREZA_JURIDICA: 2011"
"EMBRATUR - INSTITUTO BRASILEIRO DE TURISMO",,33.741.794/0001-01,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500005358 | DT_CADASTRO: 17/09/2013 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA BRASIL DE COMUNICACAO S.A - EBC",,09.168.704/0001-42,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500003487 | DT_CADASTRO: 05/11/2007 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA BRASILEIRA DE ADMINISTRACAO DE PETROLEO E GAS NATUARAL S. A - PRE-SAL PETROLEO S.A - PPSA",,18.738.727/0001-36,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500005315 | DT_CADASTRO: 23/08/2013 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA BRASILEIRA DE CORREIOS E TELEGRAFOS",,34.028.316/0001-03,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000305 | DT_CADASTRO: 22/03/1969 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA BRASILEIRA DE HEMODERIVADOS E BIOTECNOLOGIA",,07.607.851/0001-46,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500002731 | DT_CADASTRO: 24/05/2005 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA BRASILEIRA DE INFRAESTRUTURA AEROPORTUARIA - INFRAERO",,00.352.294/0001-10,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000356 | DT_CADASTRO: 12/06/1973 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA BRASILEIRA DE PARTICIPACOES EM ENERGIA NUCLEAR E BINACIONAL S.A. - ENBPAR",,43.913.162/0001-23,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500009183 | DT_CADASTRO: 13/09/2021 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA BRASILEIRA DE PESQUISA AGROPECUARIA - EMBRAPA",,00.348.003/0001-10,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000763 | DT_CADASTRO: 24/04/1973 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA BRASILEIRA DE PLANEJAMENTO DE TRANSPORTES GEIPOT",,00.366.914/0001-70,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000321 | DT_CADASTRO: 13/11/1973 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA BRASILEIRA DE SERVICOS HOSPITALARES - EBSERH",,15.126.437/0001-43,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500004734 | DT_CADASTRO: 01/03/2012 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA DE ASSISTENCIA TECNICA E EXTENSAO RURAL DO DISTRITO FEDERAL EMATER/DF",,00.509.612/0001-04,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000879 | DT_CADASTRO: 11/05/1978 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA DE PESQUISA ENERGETICA -EPE",,06.977.747/0001-80,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500005030 | DT_CADASTRO: 02/05/2005 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA DE TECNOLOGIA E INFORMACOES DA PREVIDENCIA S.A. - DATAPREV",,42.422.253/0001-01,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500003339 | DT_CADASTRO: 15/04/1975 | COD_NATUREZA_JURIDICA: 2011"
"EMPRESA GESTORA DE ATIVOS S.A - EMGEA",,04.527.335/0001-13,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300006512 | DT_CADASTRO: 28/06/2001 | COD_NATUREZA_JURIDICA: 2011"
"FINANCIADORA DE ESTUDOS E PROJETOS FINEP",,33.749.086/0001-09,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000283 | DT_CADASTRO: 22/05/1975 | COD_NATUREZA_JURIDICA: 2011"
"INDUSTRIA DE MATERIAL BELICO DO BRASIL - IMBEL",,00.444.232/0001-39,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000275 | DT_CADASTRO: 08/04/1976 | COD_NATUREZA_JURIDICA: 2011"
"OK BENFICA COMPANHIA DISTRIBUIDORA DE TITULOS E VALORES MOBILIARIOS",,33.829.953/0001-16,sociedade_economia_mista,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300003203 | DT_CADASTRO: 16/03/1983 | COD_NATUREZA_JURIDICA: 2038"
"PROFLORA S/A FLORESTAMENTO E REFLORESTAMENTO EM LIQUIDACAO",,00.338.079/0001-65,sociedade_economia_mista,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300001804 | DT_CADASTRO: 28/12/1972 | COD_NATUREZA_JURIDICA: 2038"
"SCP SOCIEDADE EM CONTA DE PARTICIPACAO",,,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500002839 | DT_CADASTRO: 05/10/2006 | COD_NATUREZA_JURIDICA: 2011"
"SERVICO FEDERAL DE PROCESSAMENTO DE DADOS - SERPRO",,33.683.111/0001-07,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53500000941 | DT_CADASTRO: 29/04/1993 | COD_NATUREZA_JURIDICA: 2011"
"SOCIEDADE DE ABASTECIMENTO DE BRASILIA S/A SAB  - EM LIQUIDACAO",,00.037.226/0001-67,sociedade_economia_mista,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300001561 | DT_CADASTRO: 13/07/1962 | COD_NATUREZA_JURIDICA: 2038"
"SOCIEDADE DE TRANSPORTES COLETIVOS DE BRASILIA LTDA",,00.037.127/0001-85,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53200002078 | DT_CADASTRO: 17/05/1961 | COD_NATUREZA_JURIDICA: 2011"
"TELEBRAS COPA S.A.",,17.729.836/0001-24,sociedade_economia_mista,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300014680 | DT_CADASTRO: 07/03/2013 | COD_NATUREZA_JURIDICA: 2038"
"TELECOMUNICACOES BRASILEIRAS S/A TELEBRAS",,00.336.701/0001-04,sociedade_economia_mista,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300002231 | DT_CADASTRO: 16/11/1972 | COD_NATUREZA_JURIDICA: 2038"
"VALEC - ENGENHARIA CONSTRUCOES E FERROVIAS S/A",,42.150.664/0001-87,empresa_publica,,,,"Cadastro oficial de empresas publicas e sociedades de economia mista ativas ate dezembro de 2025",,,,"NIRE: 53300010307 | DT_CADASTRO: 17/04/2009 | COD_NATUREZA_JURIDICA: 2011"
```
