<?php
declare(strict_types=1);

/**
 * Selector
 *
 * Escolhe quais tokens da letra se tornam lacunas.
 *
 * Regras (Req. 2):
 *   - Filtra tokens elegíveis (palavra > 2 chars, contém letra — definido pelo Tokenizer)
 *   - Seleciona entre 20% e 30% dos elegíveis, arredondado para inteiro mais próximo, mínimo 3
 *   - Evita mais de 2 lacunas consecutivas no array de tokens original
 *   - Usa mt_srand(time()) para variedade entre requisições distintas
 *
 * Para "Tentar novamente" (Req. 5.3):
 *   - Recebe $previousIndices e tenta até 10 sorteios para obter conjunto diferente
 *   - Se após 10 sorteios nenhum conjunto diferente for encontrado, usa o da 10ª tentativa
 */
class Selector
{
    /** Percentual mínimo de tokens elegíveis a selecionar. */
    private const PCT_MIN = 0.20;

    /** Percentual máximo de tokens elegíveis a selecionar. */
    private const PCT_MAX = 0.30;

    /** Número mínimo absoluto de lacunas. */
    private const MIN_GAPS = 3;

    /** Máximo de lacunas consecutivas permitidas. */
    private const MAX_CONSECUTIVE = 2;

    /** Máximo de tentativas para "Tentar novamente". */
    private const MAX_RETRY_ATTEMPTS = 10;

    /**
     * Seleciona os índices (no array $tokens) que se tornarão lacunas.
     *
     * @param  array<int, array>  $tokens          Array de tokens do Tokenizer
     * @param  array<int>         $previousIndices Índices da tentativa anterior (vazio = primeira vez)
     * @return array<int>         Índices selecionados, em ordem crescente
     *
     * @throws RuntimeException Se não houver elegíveis suficientes (< MIN_GAPS)
     */
    public function select(array $tokens, array $previousIndices = []): array
    {
        $eligibleIndices = $this->getEligibleIndices($tokens);

        $totalEligible = count($eligibleIndices);
        if ($totalEligible < self::MIN_GAPS) {
            throw new RuntimeException(
                'A letra não possui palavras elegíveis suficientes para gerar o exercício ' .
                '(mínimo ' . self::MIN_GAPS . ' palavras com mais de 2 caracteres).'
            );
        }

        $targetCount = $this->calculateTargetCount($totalEligible);

        // Semente baseada no tempo para variedade entre requisições
        mt_srand((int) (microtime(true) * 1000));

        if (empty($previousIndices)) {
            // Primeira vez: um único sorteio
            return $this->drawOnce($eligibleIndices, $targetCount);
        }

        // "Tentar novamente": até MAX_RETRY_ATTEMPTS sorteios para obter conjunto distinto
        return $this->drawDistinct($eligibleIndices, $targetCount, $previousIndices);
    }

    // -----------------------------------------------------------------------
    // Métodos privados
    // -----------------------------------------------------------------------

    /**
     * Retorna os índices de tokens elegíveis no array original.
     *
     * @param  array<int, array>  $tokens
     * @return array<int>
     */
    private function getEligibleIndices(array $tokens): array
    {
        $indices = [];
        foreach ($tokens as $i => $token) {
            if ($token['eligible'] === true) {
                $indices[] = $i;
            }
        }
        return $indices;
    }

    /**
     * Calcula quantas lacunas devem ser criadas (entre 20% e 30%, mínimo MIN_GAPS).
     */
    private function calculateTargetCount(int $totalEligible): int
    {
        $min = (int) round($totalEligible * self::PCT_MIN);
        $max = (int) round($totalEligible * self::PCT_MAX);

        // Garante pelo menos MIN_GAPS
        $min = max($min, self::MIN_GAPS);
        $max = max($max, self::MIN_GAPS);

        // Escolhe aleatoriamente dentro do intervalo [min, max]
        return mt_rand($min, $max);
    }

    /**
     * Realiza um único sorteio e aplica a restrição de consecutividade.
     *
     * @param  array<int>  $eligibleIndices Índices elegíveis no array de tokens
     * @param  int         $targetCount     Quantidade desejada de lacunas
     * @return array<int>  Índices selecionados, ordenados
     */
    private function drawOnce(array $eligibleIndices, int $targetCount): array
    {
        shuffle($eligibleIndices);
        $selected = array_slice($eligibleIndices, 0, $targetCount);
        sort($selected);
        return $this->enforceConsecutiveLimit($eligibleIndices, $selected);
    }

    /**
     * Tenta até MAX_RETRY_ATTEMPTS sorteios para obter conjunto diferente do anterior.
     *
     * @param  array<int>  $eligibleIndices
     * @param  int         $targetCount
     * @param  array<int>  $previousIndices
     * @return array<int>
     */
    private function drawDistinct(array $eligibleIndices, int $targetCount, array $previousIndices): array
    {
        $prevSet = $previousIndices;
        sort($prevSet);

        $lastDraw = [];
        for ($attempt = 0; $attempt < self::MAX_RETRY_ATTEMPTS; $attempt++) {
            shuffle($eligibleIndices);
            $candidate = array_slice($eligibleIndices, 0, $targetCount);
            sort($candidate);
            $candidate = $this->enforceConsecutiveLimit($eligibleIndices, $candidate);

            $lastDraw = $candidate;

            if ($candidate !== $prevSet) {
                return $candidate;
            }
        }

        // Após 10 tentativas sem sucesso, usa o resultado da 10ª
        return $lastDraw;
    }

    /**
     * Remove lacunas em excesso quando há mais de MAX_CONSECUTIVE consecutivas.
     *
     * Estratégia: percorre os índices selecionados em ordem; ao detectar uma
     * sequência de MAX_CONSECUTIVE+1 índices consecutivos no array de tokens,
     * remove o índice excedente e tenta substituí-lo por outro elegível ainda
     * não selecionado (se disponível), mantendo o tamanho original quando possível.
     *
     * @param  array<int>  $eligibleIndices Todos os índices elegíveis (para busca de substitutos)
     * @param  array<int>  $selected        Índices selecionados, já ordenados
     * @return array<int>  Índices corrigidos, ordenados
     */
    private function enforceConsecutiveLimit(array $eligibleIndices, array $selected): array
    {
        // Pool de substitutos: elegíveis ainda não selecionados, em ordem aleatória
        $selectedSet  = array_flip($selected);
        $substitutes  = array_values(array_diff($eligibleIndices, $selected));
        shuffle($substitutes);
        $subIdx       = 0;

        $result = $selected; // já ordenado

        $changed = true;
        while ($changed) {
            $changed = false;
            $n = count($result);

            for ($i = 0; $i <= $n - (self::MAX_CONSECUTIVE + 1); $i++) {
                // Verifica se result[$i .. $i+MAX_CONSECUTIVE] são consecutivos no token array
                $allConsecutive = true;
                for ($j = $i; $j < $i + self::MAX_CONSECUTIVE; $j++) {
                    if ($result[$j + 1] !== $result[$j] + 1) {
                        $allConsecutive = false;
                        break;
                    }
                }

                if ($allConsecutive) {
                    // Remove o (MAX_CONSECUTIVE+1)-ésimo da sequência
                    $removePos = $i + self::MAX_CONSECUTIVE;
                    $removedIndex = $result[$removePos];
                    array_splice($result, $removePos, 1);

                    unset($selectedSet[$removedIndex]);

                    // Tenta substituir por um elegível que não crie uma nova sequência longa
                    while ($subIdx < count($substitutes)) {
                        $sub = $substitutes[$subIdx++];
                        if (isset($selectedSet[$sub])) {
                            continue;
                        }

                        $candidate = $result;
                        $candidate[] = $sub;
                        sort($candidate);

                        if ($this->hasTooManyConsecutive($candidate)) {
                            continue;
                        }

                        $result = $candidate;
                        $selectedSet[$sub] = true;
                        break;
                    }

                    sort($result);
                    $n       = count($result);
                    $changed = true;
                    break; // reinicia o loop externo
                }
            }
        }

        return $result;
    }

    /**
     * Verifica se há uma sequência maior que MAX_CONSECUTIVE.
     *
     * @param  array<int> $indices Índices selecionados, já ordenados ou não
     */
    private function hasTooManyConsecutive(array $indices): bool
    {
        sort($indices);

        $run = 1;
        $count = count($indices);
        for ($i = 1; $i < $count; $i++) {
            if ($indices[$i] === $indices[$i - 1] + 1) {
                $run++;
                if ($run > self::MAX_CONSECUTIVE) {
                    return true;
                }
            } else {
                $run = 1;
            }
        }

        return false;
    }
}
