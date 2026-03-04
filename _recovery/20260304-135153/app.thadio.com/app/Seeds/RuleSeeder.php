<?php

namespace App\Seeds;

use App\Repositories\RuleRepository;

class RuleSeeder
{
    public static function seed(RuleRepository $repository): void
    {
        $pdo = $repository->getPdo();
        if (!$pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $existingStmt = $pdo->query("SELECT title FROM regras");
        $existing = $existingStmt ? $existingStmt->fetchAll(\PDO::FETCH_COLUMN) : [];
        $existing = array_map([self::class, 'normalizeKey'], $existing);

        $sql = "INSERT INTO regras (title, content, status) VALUES (:title, :content, :status)";
        $insert = $pdo->prepare($sql);

        foreach (self::defaults() as $row) {
            $titleKey = self::normalizeKey($row['title']);
            if (in_array($titleKey, $existing, true)) {
                continue;
            }
            $insert->execute([
                ':title' => $row['title'],
                ':content' => $row['content'],
                ':status' => $row['status'],
            ]);
        }
    }

    private static function defaults(): array
    {
        return [
            [
                'title' => 'Consignação Retrato Brechó',
                'content' => <<<'TEXT'
O Retrato Brechó também recebe os produtos de vocês que fazem parte dessa comunidade!

No momento só aceitamos produtos de quem mora no Distrito Federal.

É simples fazer consignação com a gente. Leia as instruções e regrinhas:

1) Contato
Você pode optar por trazer pessoalmente após agendamento via Direct do Instagram ou Whatsapp; ou então enviar as fotos dos produtos nos canais citados acima, para uma avaliação prévia.

2) Tempo de anúncio
Após 2 meses de exposição na loja, aplicamos desconto de 30% nos produtos que ainda estiverem disponíveis. Caso sejam vendidos com este desconto, repassamos o valor para você referente ao valor promocional do produto.

3) Devolução
Após 3 meses de exposição na loja, nós entraremos em contato para que retire os produtos remanescentes em até 10 dias. Caso não retire no prazo estipulado, encaminhamos para doação.

As Retratetes são exigentes, você sabe disso, então os produtos devem estar em ótimas condições.

Regrinhas para produtos normais:

- Aceitamos a partir de 8 produtos e no máximo 30 produtos.
- Traga seus produtos recém higienizados.
- Não aceitamos produtos com avarias, então avalie seus itens com cuidado.
- Fazemos curadoria, mas não se preocupe se nem todos os produtos forem aceitos para consignação.
- 40% do valor de venda do produto fica para você.
- Repassamos o valor por pix ou crédito para usar no site/loja, você escolhe :)
- O repasse é feito nos 5 primeiros dias úteis do mês subsequente ao mês das vendas.
- O Retrato Brechó fica autorizado a ajustar os valores sugeridos entre 10 a 15% para mais ou para menos sem necessidade de aprovação prévia da fornecedora.

Regrinhas para produtos de luxo:

- Aceitamos apenas itens originais.
- Produtos precisam estar higienizados.
- Precisa estar em bom/ótimo estado.
- 65% do valor de venda do produto fica para você.
- Repassamos o valor por pix ou crédito para usar no site, você escolhe :)
- O repasse é feito nos cinco primeiros dias úteis do mês subsequente ao mês das vendas.

O que não aceitamos:

- Produtos sujos.
- Réplicas, produtos não originais.
- Produtos com avarias e/ou manchas destacadas.
- Itens danificados ou rasgados.
- Produtos em courino (Couro sintético/Ecológico).
- Produtos íntimos.
- Biquínis usados.
- Couro vegetal e/ou sintético.
- Produtos infantis.
TEXT,
                'status' => 'ativo',
            ],
        ];
    }

    private static function normalizeKey(string $value): string
    {
        $value = trim($value);
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value);
        }
        return strtolower($value);
    }
}
