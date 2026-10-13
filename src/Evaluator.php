<?php
declare(strict_types=1);

/**
 * Evaluator
 *
 * Avalia as respostas do usuário comparando com o gabarito via distância de Levenshtein.
 *
 * Classificações (Req. 4):
 *   'correct'  — distância = 0 (case-insensitive)
 *   'close'    — distância > 0, <= 2, e token original com >= 4 caracteres
 *   'wrong'    — demais casos, ou campo vazio
 *
 * Cada item do array retornado:
 *   [
 *     'status'       => 'correct' | 'close' | 'wrong',
 *     'correct_word' => string,   // palavra correta do gabarito
 *     'answer'       => string,   // resposta fornecida pelo usuário
 *   ]
 */
class Evaluator
{
    /**
     * Avalia um conjunto de respostas contra o gabarito.
     *
     * @param  array<int, string>  $answers   Respostas digitadas (uma por lacuna)
     * @param  array<int, string>  $gabarito  Palavras corretas (uma por lacuna)
     * @return array<int, array{status: string, correct_word: string, answer: string}>
     *
     * @throws InvalidArgumentException Se count($answers) !== count($gabarito) (Req. 4.9)
     */
    public function evaluate(array $answers, array $gabarito): array
    {
        if (count($answers) !== count($gabarito)) {
            throw new InvalidArgumentException(
                'Incompatibilidade entre campos e gabarito: ' .
                count($answers) . ' resposta(s) para ' . count($gabarito) . ' lacuna(s).'
            );
        }

        $results = [];
        $gabarito = array_values($gabarito);
        $answers  = array_values($answers);

        foreach ($gabarito as $pos => $correctWord) {
            $answer = $answers[$pos] ?? '';
            $results[] = [
                'status'       => $this->classify($answer, $correctWord),
                'correct_word' => $correctWord,
                'answer'       => $answer,
            ];
        }

        return $results;
    }

    // -----------------------------------------------------------------------
    // Privados
    // -----------------------------------------------------------------------

    /**
     * Classifica uma resposta individual.
     *
     * Req. 4.8: campo vazio => wrong (sem calcular distância)
     * Req. 4.2: distância = 0 => correct
     * Req. 4.3: distância <= 2 e len(token) >= 4 => close
     * Req. 4.4: demais => wrong
     */
    private function classify(string $answer, string $correctWord): string
    {
        // Campo vazio → incorreto imediatamente (Req. 4.8)
        if (trim($answer) === '') {
            return 'wrong';
        }

        $answerNorm  = strtolower(trim($answer));
        $correctNorm = strtolower($correctWord);

        $distance = levenshtein($answerNorm, $correctNorm);

        if ($distance === 0) {
            return 'correct';
        }

        $tokenLen = function_exists('mb_strlen')
            ? mb_strlen($correctWord, 'UTF-8')
            : strlen($correctWord);

        if ($distance <= 2 && $tokenLen >= 4) {
            return 'close';
        }

        return 'wrong';
    }
}
