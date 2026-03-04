<?php

namespace App\Seeds;

use App\Repositories\CommemorativeDateRepository;

class CommemorativeDateSeeder
{
    public static function seed(CommemorativeDateRepository $repository): void
    {
        $pdo = $repository->getPdo();
        if (!$pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $sql = "INSERT INTO datas_comemorativas (name, day, month, year, scope, category, description, source, status)
                VALUES (:name, :day, :month, :year, :scope, :category, :description, :source, :status)";
        $insert = $pdo->prepare($sql);

        $existingStmt = $pdo->query("SELECT CONCAT(name, '|', day, '|', month, IFNULL(year,'0')) AS keyid FROM datas_comemorativas");
        $existing = $existingStmt ? $existingStmt->fetchAll(\PDO::FETCH_COLUMN) : [];

        foreach (self::defaults() as $row) {
            $key = $row['name'] . '|' . ($row['day'] ?? 0) . '|' . ($row['month'] ?? 0) . '|' . ($row['year'] ?? '0');
            if (in_array($key, $existing, true)) {
                continue;
            }

            $insert->execute([
                ':name' => $row['name'],
                ':day' => $row['day'] ?? 0,
                ':month' => $row['month'] ?? 0,
                ':year' => $row['year'] ?? null,
                ':scope' => $row['scope'] ?? 'brasil',
                ':category' => $row['category'] ?? null,
                ':description' => $row['description'] ?? null,
                ':source' => $row['source'] ?? 'import',
                ':status' => $row['status'] ?? 'ativo',
            ]);
        }
    }

    private static function defaults(): array
    {
        // Calendar entries (Brazil + internationally recognized in Brazil)
        // Each item: name, day, month, optional year, scope, category, description
        return [
            // Janeiro
            ['name' => 'Confraternização Universal (Ano-Novo); Dia Mundial da Paz', 'day' => 1, 'month' => 1],
            ['name' => 'Dia do Sanitarista', 'day' => 2, 'month' => 1],
            ['name' => 'Dia do Hemofílico; Dia da Abreugrafia', 'day' => 3, 'month' => 1],
            ['name' => 'Dia Mundial do Braille', 'day' => 4, 'month' => 1],
            ['name' => 'Dia Nacional da Tipografia', 'day' => 5, 'month' => 1],
            ['name' => 'Dia de Reis; Dia da Liberdade de Culto', 'day' => 6, 'month' => 1],
            ['name' => 'Dia do Fotógrafo (Dia da Fotografia)', 'day' => 8, 'month' => 1],
            ['name' => 'Dia do Fico (1822); Dia do Astronauta', 'day' => 9, 'month' => 1],
            ['name' => 'Dia do Sargento; Dia do Treinador de Futebol', 'day' => 14, 'month' => 1],
            ['name' => 'Dia do Compositor', 'day' => 15, 'month' => 1],
            ['name' => 'Dia dos Cortadores de Cana-de-Açúcar', 'day' => 16, 'month' => 1],
            ['name' => 'Dia da Manicure', 'day' => 18, 'month' => 1],
            ['name' => 'Dia do Cabeleireiro; Dia do Terapeuta Ocupacional', 'day' => 19, 'month' => 1],
            ['name' => 'Dia do Farmacêutico; Dia da Parteira Tradicional', 'day' => 20, 'month' => 1],
            ['name' => 'Dia Nacional de Combate à Intolerância Religiosa; Dia Mundial da Religião', 'day' => 21, 'month' => 1],
            ['name' => 'Dia Mundial da Educação', 'day' => 24, 'month' => 1],
            ['name' => 'Dia do Carteiro', 'day' => 25, 'month' => 1, 'description' => 'Também aniversário da cidade de São Paulo'],
            ['name' => 'Dia Internacional em Memória das Vítimas do Holocausto', 'day' => 27, 'month' => 1],
            ['name' => 'Dia do Portuário', 'day' => 28, 'month' => 1],
            ['name' => 'Dia Nacional da Visibilidade Trans', 'day' => 29, 'month' => 1],
            ['name' => 'Dia Nacional das Histórias em Quadrinhos; Dia do Engenheiro Ambiental', 'day' => 30, 'month' => 1],
            ['name' => 'Dia da Solidariedade (São João Bosco)', 'day' => 31, 'month' => 1, 'description' => 'Comemorado em alguns calendários religiosos'],

            // Fevereiro
            ['name' => 'Dia do Publicitário', 'day' => 1, 'month' => 2],
            ['name' => 'Dia de Nossa Senhora dos Navegantes; Dia de Iemanjá; Dia Mundial das Zonas Úmidas', 'day' => 2, 'month' => 2],
            ['name' => 'Dia Mundial do Câncer', 'day' => 4, 'month' => 2],
            ['name' => 'Dia do Datiloscopista; Dia do Dermatologista', 'day' => 5, 'month' => 2],
            ['name' => 'Dia Nacional de Luta dos Povos Indígenas', 'day' => 7, 'month' => 2],
            ['name' => 'Dia Mundial da Pizza', 'day' => 9, 'month' => 2],
            ['name' => 'Dia do Atleta Profissional; Dia Internacional das Mulheres e Meninas na Ciência', 'day' => 10, 'month' => 2],
            ['name' => 'Dia Internacional das Mulheres e Meninas na Ciência (ONU)', 'day' => 11, 'month' => 2],
            ['name' => 'Dia Mundial do Rádio', 'day' => 13, 'month' => 2],
            ['name' => 'Dia de São Valentim (Valentine’s Day)', 'day' => 14, 'month' => 2, 'description' => 'Celebração importada, popular internacionalmente'],
            ['name' => 'Dia do Repórter', 'day' => 16, 'month' => 2],
            ['name' => 'Dia do Esportista', 'day' => 19, 'month' => 2],
            ['name' => 'Dia Internacional da Língua Materna', 'day' => 21, 'month' => 2],
            ['name' => 'Promulgação da Primeira Constituição Republicana (1891)', 'day' => 24, 'month' => 2],
            ['name' => 'Dia do Agente Fiscal da Receita Federal; Dia Internacional do Urso Polar', 'day' => 27, 'month' => 2],
            ['name' => 'Carnaval (data móvel)', 'day' => null, 'month' => null, 'description' => 'Data móvel: terça-feira anterior à Quarta-feira de Cinzas; geralmente em fevereiro ou início de março', 'category' => 'móvel'],
            ['name' => 'Quarta-feira de Cinzas (início da Quaresma) (data móvel)', 'day' => null, 'month' => null, 'description' => 'Data móvel, 46 dias antes da Páscoa', 'category' => 'móvel'],

            // Março
            ['name' => 'Dia Mundial das Ervas Marinhas', 'day' => 1, 'month' => 3],
            ['name' => 'Dia do Fuzileiro Naval; Dia do Paleontólogo', 'day' => 7, 'month' => 3],
            ['name' => 'Dia Internacional da Mulher', 'day' => 8, 'month' => 3],
            ['name' => 'Dia do DJ', 'day' => 9, 'month' => 3],
            ['name' => 'Dia do Bibliotecário', 'day' => 12, 'month' => 3],
            ['name' => 'Dia do Consumidor', 'day' => 15, 'month' => 3],
            ['name' => 'Dia do Carpinteiro e do Marceneiro; Dia Nacional do Artesão', 'day' => 19, 'month' => 3],
            ['name' => 'Início do outono (hemisfério sul)', 'day' => 20, 'month' => 3],
            ['name' => 'Dia Internacional das Florestas; Dia Internacional para Eliminação da Discriminação Racial; Dia Internacional da Síndrome de Down; Dia Mundial da Poesia', 'day' => 21, 'month' => 3],
            ['name' => 'Dia Mundial da Água', 'day' => 22, 'month' => 3],
            ['name' => 'Dia Mundial da Meteorologia', 'day' => 23, 'month' => 3],
            ['name' => 'Dia Mundial do Teatro', 'day' => 27, 'month' => 3],
            ['name' => 'Dia do Diagramador; Dia do Revisor', 'day' => 28, 'month' => 3],
            ['name' => 'Semana Santa (Domingo de Ramos, Sexta-Feira Santa, Domingo de Páscoa) (datas móveis)', 'day' => null, 'month' => null, 'description' => 'Datas móveis entre março e abril; Sexta-Feira Santa é feriado nacional', 'category' => 'móvel'],

            // Abril
            ['name' => 'Dia da Mentira', 'day' => 1, 'month' => 4],
            ['name' => 'Dia Mundial de Conscientização do Autismo; Dia Internacional do Livro Infantil; Dia do Jornalista', 'day' => 2, 'month' => 4],
            ['name' => 'Dia Mundial da Saúde', 'day' => 7, 'month' => 4],
            ['name' => 'Dia Mundial da Astronomia; Dia Nacional do Sistema Braille', 'day' => 8, 'month' => 4],
            ['name' => 'Dia do Office Boy', 'day' => 13, 'month' => 4, 'description' => 'Comemorado em alguns locais'],
            ['name' => 'Dia Nacional do Livro Infantil (Dia de Monteiro Lobato)', 'day' => 18, 'month' => 4],
            ['name' => 'Dia do Índio', 'day' => 19, 'month' => 4],
            ['name' => 'Dia do Diplomata', 'day' => 20, 'month' => 4],
            ['name' => 'Dia de Tiradentes (feriado nacional)', 'day' => 21, 'month' => 4],
            ['name' => 'Dia do Descobrimento do Brasil; Dia Internacional da Mãe Terra (Dia Mundial da Terra)', 'day' => 22, 'month' => 4],
            ['name' => 'Dia de São Jorge; Dia do Escoteiro', 'day' => 23, 'month' => 4, 'description' => 'Feriado no RJ para São Jorge'],
            ['name' => 'Dia de Libras (Língua Brasileira de Sinais); Dia Mundial do Livro', 'day' => 24, 'month' => 4],
            ['name' => 'Dia do Contabilista (Profissional da Contabilidade)', 'day' => 25, 'month' => 4],
            ['name' => 'Dia do Goleiro', 'day' => 26, 'month' => 4],
            ['name' => 'Dia da Educação; Dia da Sogra', 'day' => 28, 'month' => 4],
            ['name' => 'Dia Internacional da Dança', 'day' => 29, 'month' => 4],
            ['name' => 'Dia Nacional da Mulher', 'day' => 30, 'month' => 4],

            // Maio
            ['name' => 'Dia do Trabalhador (feriado nacional)', 'day' => 1, 'month' => 5],
            ['name' => 'Dia do Taquígrafo', 'day' => 3, 'month' => 5],
            ['name' => 'Dia Internacional da Parteira', 'day' => 5, 'month' => 5],
            ['name' => 'Dia Nacional da Matemática; Dia do Cartógrafo; Dia do Psicanalista', 'day' => 6, 'month' => 5],
            ['name' => 'Dia do Oftalmologista; Dia Mundial do Silêncio', 'day' => 7, 'month' => 5],
            ['name' => 'Dia do Artista Plástico', 'day' => 8, 'month' => 5],
            ['name' => 'Dia do Guia de Turismo; Dia da Cozinheira', 'day' => 10, 'month' => 5],
            ['name' => 'Dia do Enfermeiro (Dia Internacional da Enfermagem)', 'day' => 12, 'month' => 5],
            ['name' => 'Dia da Abolição da Escravatura (1888); Dia de Nossa Senhora de Fátima', 'day' => 13, 'month' => 5],
            ['name' => 'Dia do Assistente Social; Dia do Zootecnista', 'day' => 15, 'month' => 5],
            ['name' => 'Dia Internacional contra a Homofobia (LGBTQI+fobia)', 'day' => 17, 'month' => 5],
            ['name' => 'Dia Nacional de Combate ao Abuso e à Exploração Sexual de Crianças e Adolescentes', 'day' => 18, 'month' => 5],
            ['name' => 'Dia do Defensor Público; Dia do Físico', 'day' => 19, 'month' => 5],
            ['name' => 'Dia do Comissário de Menores; Dia do Técnico e Auxiliar de Enfermagem; Dia do Pedagogo', 'day' => 20, 'month' => 5],
            ['name' => 'Dia do Apicultor', 'day' => 22, 'month' => 5],
            ['name' => 'Dia da Indústria; Dia do Industrial; Dia da Costureira; Dia do Massagista; Dia do Trabalhador Rural; Dia do Orgulho Nerd (Dia da Toalha)', 'day' => 25, 'month' => 5],
            ['name' => 'Dia do Revendedor Lotérico', 'day' => 26, 'month' => 5],
            ['name' => 'Dia do Profissional Liberal', 'day' => 27, 'month' => 5],
            ['name' => 'Dia Internacional de Ação pela Saúde da Mulher; Dia Nacional de Redução da Mortalidade Materna; Dia do Ceramista', 'day' => 28, 'month' => 5],
            ['name' => 'Dia do Geógrafo; Dia do Estatístico', 'day' => 29, 'month' => 5],
            ['name' => 'Dia do Geólogo; Dia do Decorador', 'day' => 30, 'month' => 5],
            ['name' => 'Dia da Aeromoça e do Comissário de Voo', 'day' => 31, 'month' => 5],
            ['name' => 'Dia das Mães (segundo domingo de maio) (móvel)', 'day' => null, 'month' => null, 'category' => 'móvel', 'description' => 'Segundo domingo de maio'],

            // Junho
            ['name' => 'Dia Internacional do Profissional de Recursos Humanos (Administrador de Pessoal)', 'day' => 3, 'month' => 6],
            ['name' => 'Dia Mundial do Meio Ambiente', 'day' => 5, 'month' => 6],
            ['name' => 'Dia do Porteiro; Dia do Tenista', 'day' => 9, 'month' => 6],
            ['name' => 'Dia da Marinha Brasileira', 'day' => 11, 'month' => 6],
            ['name' => 'Dia dos Namorados; Dia Mundial contra o Trabalho Infantil', 'day' => 12, 'month' => 6],
            ['name' => 'Dia do Químico; Dia da Imigração Japonesa no Brasil', 'day' => 18, 'month' => 6],
            ['name' => 'Dia do Cinema Brasileiro; Dia Nacional do Luto', 'day' => 19, 'month' => 6],
            ['name' => 'Dia do Advogado Trabalhista; Dia do Vigilante; Início do inverno (hemisfério sul)', 'day' => 20, 'month' => 6],
            ['name' => 'Dia do Profissional de Mídia', 'day' => 21, 'month' => 6],
            ['name' => 'Dia do Aeroviário; Dia do Orquidófilo', 'day' => 22, 'month' => 6],
            ['name' => 'Dia Olímpico (Dia do Esporte Olímpico); Dia Internacional das Mulheres na Engenharia', 'day' => 23, 'month' => 6],
            ['name' => 'Dia de São João (festa junina tradicional)', 'day' => 24, 'month' => 6, 'description' => 'Feriado em alguns estados do Nordeste'],
            ['name' => 'Dia do Metrologista', 'day' => 26, 'month' => 6],
            ['name' => 'Dia Nacional do Quadrilheiro Junino (festas juninas)', 'day' => 27, 'month' => 6],
            ['name' => 'Dia Internacional do Orgulho LGBTQIA+', 'day' => 28, 'month' => 6],
            ['name' => 'Dia de São Pedro e São Paulo; Dia do Dublador; Dia do Engenheiro de Petróleo; Dia do Papa; Dia do Pescador; Dia do Telefonista', 'day' => 29, 'month' => 6],
            ['name' => 'Corpus Christi (data móvel)', 'day' => null, 'month' => null, 'category' => 'móvel', 'description' => 'Quinta-feira, 60 dias após o Domingo de Páscoa'],

            // Julho
            ['name' => 'Independência da Bahia (1823); Dia do Bombeiro Brasileiro', 'day' => 2, 'month' => 7],
            ['name' => 'Dia do Operador de Telemarketing', 'day' => 4, 'month' => 7],
            ['name' => 'Dia Nacional do Pesquisador', 'day' => 8, 'month' => 7],
            ['name' => 'Dia do Engenheiro de Minas', 'day' => 10, 'month' => 7],
            ['name' => 'Dia do Engenheiro Florestal', 'day' => 12, 'month' => 7],
            ['name' => 'Dia Mundial do Rock; Dia do Cantor; Dia do Engenheiro de Saneamento; Dia do Compositor e Cantor Sertanejo', 'day' => 13, 'month' => 7],
            ['name' => 'Dia do Engenheiro de Aquicultura; Dia do Propagandista de Laboratório; Dia do Administrador Hospitalar', 'day' => 14, 'month' => 7],
            ['name' => 'Dia do Homem; Dia Nacional do Pecuarista', 'day' => 15, 'month' => 7],
            ['name' => 'Dia do Comerciante', 'day' => 16, 'month' => 7],
            ['name' => 'Dia do Amigo', 'day' => 20, 'month' => 7],
            ['name' => 'Dia Nacional do Garimpeiro', 'day' => 21, 'month' => 7],
            ['name' => 'Dia do Cantor Lírico', 'day' => 22, 'month' => 7],
            ['name' => 'Dia do Guarda Rodoviário', 'day' => 23, 'month' => 7],
            ['name' => 'Dia Nacional do Suinocultor', 'day' => 24, 'month' => 7],
            ['name' => 'Dia do Escritor; Dia do Motorista', 'day' => 25, 'month' => 7],
            ['name' => 'Dia Internacional da Mulher Negra Latino-Americana e Caribenha; Dia Nacional de Tereza de Benguela e da Mulher Negra', 'day' => 25, 'month' => 7],
            ['name' => 'Dia dos Avós; Dia Nacional do Arqueólogo', 'day' => 26, 'month' => 7],
            ['name' => 'Dia do Motociclista; Dia do Pediatra', 'day' => 27, 'month' => 7],
            ['name' => 'Dia do Agricultor', 'day' => 28, 'month' => 7],
            ['name' => 'Dia Mundial do Guarda-Florestal', 'day' => 31, 'month' => 7],

            // Agosto
            ['name' => 'Dia do Tintureiro', 'day' => 3, 'month' => 8],
            ['name' => 'Dia Nacional da Saúde (nascimento de Oswaldo Cruz)', 'day' => 5, 'month' => 8],
            ['name' => 'Dia Nacional dos Profissionais da Educação', 'day' => 6, 'month' => 8],
            ['name' => 'Dia do Estudante; Dia do Advogado; Dia do Garçom; Dia do Magistrado', 'day' => 11, 'month' => 8],
            ['name' => 'Dia Mundial do Canhoto; Dia do Economista', 'day' => 13, 'month' => 8],
            ['name' => 'Dia do Cardiologista', 'day' => 14, 'month' => 8],
            ['name' => 'Assunção de Nossa Senhora; Dia do Analista de Sistemas', 'day' => 15, 'month' => 8],
            ['name' => 'Dia do Filósofo', 'day' => 16, 'month' => 8],
            ['name' => 'Dia do Artista de Teatro; Dia do Historiador', 'day' => 19, 'month' => 8],
            ['name' => 'Dia do Folclore', 'day' => 22, 'month' => 8],
            ['name' => 'Dia do Soldado; Dia do Feirante', 'day' => 25, 'month' => 8],
            ['name' => 'Dia do Psicólogo; Dia do Corretor de Imóveis', 'day' => 27, 'month' => 8],
            ['name' => 'Dia dos Bancários', 'day' => 28, 'month' => 8],
            ['name' => 'Dia do Nutricionista', 'day' => 31, 'month' => 8],
            ['name' => 'Dia dos Pais (segundo domingo de agosto) (móvel)', 'day' => null, 'month' => null, 'category' => 'móvel'],

            // Setembro
            ['name' => 'Dia do Profissional de Educação Física', 'day' => 1, 'month' => 9],
            ['name' => 'Dia do Florista; Dia do Repórter Fotográfico', 'day' => 2, 'month' => 9],
            ['name' => 'Dia do Biólogo', 'day' => 3, 'month' => 9],
            ['name' => 'Dia da Amazônia', 'day' => 5, 'month' => 9],
            ['name' => 'Dia do Alfaiate', 'day' => 6, 'month' => 9],
            ['name' => 'Dia da Independência do Brasil (feriado nacional)', 'day' => 7, 'month' => 9],
            ['name' => 'Natividade de Nossa Senhora', 'day' => 8, 'month' => 9],
            ['name' => 'Dia do Administrador; Dia do Médico Veterinário', 'day' => 9, 'month' => 9],
            ['name' => 'Dia Nacional do Cerrado', 'day' => 11, 'month' => 9],
            ['name' => 'Dia do Programador', 'day' => 13, 'month' => 9],
            ['name' => 'Dia do Caminhoneiro', 'day' => 16, 'month' => 9],
            ['name' => 'Dia Nacional do Educador Social; Dia do Ortopedista', 'day' => 19, 'month' => 9],
            ['name' => 'Dia do Engenheiro Químico', 'day' => 20, 'month' => 9],
            ['name' => 'Dia da Árvore; Dia Nacional de Luta das Pessoas com Deficiência; Dia Internacional da Paz', 'day' => 21, 'month' => 9],
            ['name' => 'Início da primavera (hemisfério sul); Dia do Contador; Dia do Soldador; Dia do Técnico em Edificações', 'day' => 22, 'month' => 9],
            ['name' => 'Dia do Trânsito (Dia Nacional do Trânsito)', 'day' => 25, 'month' => 9],
            ['name' => 'Dia Nacional dos Surdos', 'day' => 26, 'month' => 9],
            ['name' => 'Dia de São Cosme e Damião; Dia do Encanador; Dia Mundial do Turismo', 'day' => 27, 'month' => 9],
            ['name' => 'Dia da Secretária; Dia Mundial do Tradutor', 'day' => 30, 'month' => 9],

            // Outubro
            ['name' => 'Dia Internacional da Pessoa Idosa; Dia Nacional do Idoso', 'day' => 1, 'month' => 10],
            ['name' => 'Dia do Vendedor; Dia do Representante Comercial; Dia do Vereador', 'day' => 1, 'month' => 10],
            ['name' => 'Dia Mundial do Dentista', 'day' => 3, 'month' => 10],
            ['name' => 'Dia do Agente Comunitário de Saúde; Dia do Barman; Dia do Médico do Trabalho', 'day' => 4, 'month' => 10],
            ['name' => 'Dia Mundial dos Professores (UNESCO)', 'day' => 5, 'month' => 10],
            ['name' => 'Dia do Compositor Brasileiro', 'day' => 7, 'month' => 10],
            ['name' => 'Dia de Nossa Senhora Aparecida; Dia das Crianças (feriado nacional)', 'day' => 12, 'month' => 10],
            ['name' => 'Dia Nacional do Fisioterapeuta; Dia do Terapeuta Ocupacional', 'day' => 13, 'month' => 10],
            ['name' => 'Dia do Professor', 'day' => 15, 'month' => 10],
            ['name' => 'Dia do Anestesiologista', 'day' => 16, 'month' => 10],
            ['name' => 'Dia do Médico; Dia do Estivador; Dia do Pintor', 'day' => 18, 'month' => 10],
            ['name' => 'Dia do Guarda Noturno; Dia do Profissional de Informática; Dia do Profissional de Tecnologia da Informação; Dia do Operador de Caixa', 'day' => 19, 'month' => 10],
            ['name' => 'Dia do Arquivista; Dia do Maquinista; Dia do Poeta; Dia do Controlador de Tráfego Aéreo', 'day' => 20, 'month' => 10],
            ['name' => 'Dia do Enólogo; Dia do Paraquedista', 'day' => 22, 'month' => 10],
            ['name' => 'Dia do Aviador (Dia da Força Aérea Brasileira)', 'day' => 23, 'month' => 10],
            ['name' => 'Dia do Dentista; Dia do Engenheiro Civil; Dia do Sapateiro', 'day' => 25, 'month' => 10],
            ['name' => 'Dia do Trabalhador da Construção Civil', 'day' => 26, 'month' => 10],
            ['name' => 'Dia do Engenheiro Agrícola', 'day' => 27, 'month' => 10],
            ['name' => 'Dia do Servidor Público', 'day' => 28, 'month' => 10],
            ['name' => 'Dia do Saci; Dia da Reforma Protestante; Dia das Bruxas (Halloween)', 'day' => 31, 'month' => 10],

            // Novembro
            ['name' => 'Dia de Todos os Santos', 'day' => 1, 'month' => 11],
            ['name' => 'Dia de Finados (feriado nacional)', 'day' => 2, 'month' => 11],
            ['name' => 'Dia da Instituição do Direito de Voto da Mulher (1930)', 'day' => 3, 'month' => 11],
            ['name' => 'Dia do Inventor', 'day' => 4, 'month' => 11],
            ['name' => 'Dia do Designer Gráfico; Dia do Protético; Dia do Radioamador; Dia do Técnico Agrícola; Dia do Técnico em Eletrônica; Dia Nacional da Língua Portuguesa', 'day' => 5, 'month' => 11],
            ['name' => 'Dia Nacional da Cultura (nascimento de Rui Barbosa)', 'day' => 5, 'month' => 11],
            ['name' => 'Dia do Radialista', 'day' => 7, 'month' => 11],
            ['name' => 'Dia do Radiologista', 'day' => 8, 'month' => 11],
            ['name' => 'Dia do Hoteleiro; Dia Nacional do Inventor; Dia Internacional contra o Fascismo e o Antissemitismo', 'day' => 9, 'month' => 11],
            ['name' => 'Dia do Diretor de Escola (comemoração estadual em alguns locais)', 'day' => 12, 'month' => 11],
            ['name' => 'Dia Nacional da Alfabetização; Dia do Vendedor Ambulante', 'day' => 14, 'month' => 11],
            ['name' => 'Proclamação da República (1889) (feriado nacional); Dia Nacional da Umbanda', 'day' => 15, 'month' => 11],
            ['name' => 'Dia Internacional dos Estudantes', 'day' => 17, 'month' => 11],
            ['name' => 'Dia do Conselheiro Tutelar; Dia Nacional do Notário e Registrador', 'day' => 18, 'month' => 11],
            ['name' => 'Dia da Bandeira; Dia Internacional do Homem', 'day' => 19, 'month' => 11],
            ['name' => 'Dia Nacional da Consciência Negra; Dia do Biomédico; Dia do Técnico em Contabilidade', 'day' => 20, 'month' => 11],
            ['name' => 'Dia do Músico (Santa Cecília)', 'day' => 22, 'month' => 11],
            ['name' => 'Dia do Engenheiro Eletricista', 'day' => 23, 'month' => 11],
            ['name' => 'Dia Internacional para a Eliminação da Violência contra a Mulher; Dia da Baiana de Acarajé', 'day' => 25, 'month' => 11],
            ['name' => 'Dia do Técnico em Segurança do Trabalho', 'day' => 27, 'month' => 11],
            ['name' => 'Dia do Evangélico', 'day' => 30, 'month' => 11],

            // Dezembro
            ['name' => 'Dia Mundial de Combate à AIDS', 'day' => 1, 'month' => 12],
            ['name' => 'Dia Nacional das Relações Públicas', 'day' => 2, 'month' => 12],
            ['name' => 'Dia do Delegado de Polícia; Dia do Perito Criminal; Dia do Pedicure; Dia do Trabalhador em Minas de Carvão', 'day' => 3, 'month' => 12],
            ['name' => 'Dia do Médico de Família e Comunidade', 'day' => 5, 'month' => 12],
            ['name' => 'Dia Nacional da Silvicultura; Dia Internacional da Aviação Civil', 'day' => 7, 'month' => 12],
            ['name' => 'Dia da Justiça; Dia de Nossa Senhora da Conceição', 'day' => 8, 'month' => 12, 'description' => 'Comemoração do Poder Judiciário; feriado municipal em várias cidades'],
            ['name' => 'Dia do Fonoaudiólogo', 'day' => 9, 'month' => 12],
            ['name' => 'Dia Internacional dos Direitos Humanos; Dia da Bíblia', 'day' => 10, 'month' => 12],
            ['name' => 'Dia Universal do Palhaço', 'day' => 10, 'month' => 12],
            ['name' => 'Dia do Engenheiro Civil', 'day' => 11, 'month' => 12],
            ['name' => 'Dia do Marinheiro; Dia do Pedreiro; Dia do Arquiteto; Dia do Jardineiro; Dia do Engenheiro de Produção', 'day' => 13, 'month' => 12],
            ['name' => 'Dia do Engenheiro Avaliador e Perito de Engenharia', 'day' => 13, 'month' => 12],
            ['name' => 'Dia do Museólogo', 'day' => 18, 'month' => 12],
            ['name' => 'Dia do Mecânico', 'day' => 20, 'month' => 12],
            ['name' => 'Início do verão (hemisfério sul)', 'day' => 21, 'month' => 12],
            ['name' => 'Véspera de Natal', 'day' => 24, 'month' => 12],
            ['name' => 'Natal (feriado nacional)', 'day' => 25, 'month' => 12],
            ['name' => 'Dia do Salva-Vidas; Dia do Petroquímico', 'day' => 28, 'month' => 12],
            ['name' => 'Véspera de Ano-Novo', 'day' => 31, 'month' => 12],
        ];
    }
}
