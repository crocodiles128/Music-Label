<?php
declare(strict_types=1);

/**
 * Renderer
 *
 * Gera o HTML do exercício a partir dos tokens e dos índices de lacunas.
 *
 * Responsabilidades:
 *   - Renderizar a letra com <input> nos lugares das lacunas
 *   - Dimensionar cada input proporcionalmente ao token (3-40ch, Req. 3.2)
 *   - Preservar quebras de linha originais (Req. 3.3)
 *   - Manter pontuação adjacente fora do input (Req. 3.4)
 *   - Nunca expor o gabarito no HTML (Req. 3.6)
 *   - aria-label="Lacuna N" em cada input (Req. 6.4)
 *   - renderResult() com feedback visual (Req. 4.5-4.6), implementado na Task 7
 */
class Renderer
{
    /** Largura mínima de um campo em caracteres. */
    private const WIDTH_MIN = 5;

    /** Largura máxima de um campo em caracteres. */
    private const WIDTH_MAX = 48;

    // -----------------------------------------------------------------------
    // Renderização do exercício (campos em branco)
    // -----------------------------------------------------------------------

    /**
     * Renderiza a letra com lacunas como campos de texto.
     *
     * @param  array<int, array>  $tokens  Array de tokens do Tokenizer
     * @param  array<int>         $indices Índices que são lacunas
     * @return string HTML do exercício (sem gabarito exposto)
     *
     * @throws InvalidArgumentException Se $tokens estiver vazio
     */
    public function renderExercise(array $tokens, array $indices): string
    {
        if (empty($tokens)) {
            return $this->errorBlock('O conteúdo da letra é inválido.');
        }

        if (empty($indices)) {
            return $this->errorBlock('Nenhuma palavra foi ocultada. O exercício não pode ser exibido.');
        }

        $gapSet    = array_flip($indices); // índice => posição para lookup O(1)
        $gapNumber = 0;                    // contador ordinal das lacunas (1-based)
        $html      = '';

        foreach ($tokens as $i => $token) {
            if ($token['is_newline']) {
                $html .= '<br>';
                continue;
            }

            $pb = htmlspecialchars($token['punct_before'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $pa = htmlspecialchars($token['punct_after'],  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            if (isset($gapSet[$i])) {
                // Token é lacuna: renderiza input
                $gapNumber++;
                $width = $this->fieldWidth($token['word']);
                $label = 'Lacuna ' . $gapNumber;

                $html .= $pb;
                $html .= '<span class="blank-wrapper">';
                $html .= sprintf(
                    '<input type="text" name="resp[]" class="blank-field border-gray-400"'
                    . ' style="width:%dch" aria-label="%s" autocomplete="off" autocorrect="off"'
                    . ' autocapitalize="off" spellcheck="false">',
                    $width,
                    htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                );
                $html .= '</span>';
                $html .= $pa;
            } else {
                // Token normal: exibe palavra com pontuação
                $word  = htmlspecialchars($token['word'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html .= $pb . $word . $pa;
            }

            // Espaço após cada token (exceto antes de newline — o próximo token tratará)
            $html .= ' ';
        }

        return $html;
    }

    // -----------------------------------------------------------------------
    // Renderização do resultado com feedback (Task 7)
    // -----------------------------------------------------------------------

    /**
     * Renderiza a letra com inputs preenchidos e feedback visual por cor.
     *
     * @param  array<int, array>  $tokens   Array de tokens do Tokenizer
     * @param  array<int>         $indices  Índices que são lacunas
     * @param  array<int, array>  $results  Resultados do Evaluator: [['status'=>..., 'correct_word'=>...], ...]
     * @param  array<int, string> $answers  Respostas digitadas pelo usuário
     * @return string HTML com feedback visual
     */
    public function renderResult(array $tokens, array $indices, array $results, array $answers): string
    {
        if (empty($tokens)) {
            return $this->errorBlock('O conteúdo da letra é inválido.');
        }

        // Mapeia índice-do-token => posição-na-lacuna (0-based) para lookup O(1)
        $gapPositionByIndex = [];
        foreach ($indices as $gapPos => $tokenIdx) {
            $gapPositionByIndex[$tokenIdx] = $gapPos;
        }

        // Mapa de classes CSS por status (Req. 4.5)
        $statusClass = [
            'correct' => 'correct',
            'close'   => 'close',
            'wrong'   => 'wrong',
        ];

        $gapNumber = 0; // ordinal 1-based para aria-label
        $html      = '';

        foreach ($tokens as $i => $token) {
            if ($token['is_newline']) {
                $html .= '<br>';
                continue;
            }

            $pb = htmlspecialchars($token['punct_before'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $pa = htmlspecialchars($token['punct_after'],  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            if (isset($gapPositionByIndex[$i])) {
                $gapPos    = $gapPositionByIndex[$i];
                $result    = $results[$gapPos] ?? ['status' => 'wrong', 'correct_word' => ''];
                $answer    = $answers[$gapPos]  ?? '';
                $gapNumber++;

                $status    = $result['status'];
                $cssClass  = $statusClass[$status] ?? 'wrong';
                $width     = $this->fieldWidth($token['word']);
                $label     = 'Lacuna ' . $gapNumber;

                $html .= $pb;
                $html .= '<span class="blank-wrapper">';

                // Input preenchido, somente-leitura, com classe de cor (Req. 4.5)
                $html .= sprintf(
                    '<input type="text" class="blank-field %s" style="width:%dch"'
                    . ' aria-label="%s" value="%s" readonly>',
                    $cssClass,
                    $width,
                    htmlspecialchars($label,  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    htmlspecialchars($answer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                );

                // Palavra correta abaixo para close e wrong (Req. 4.6)
                if ($status === 'close' || $status === 'wrong') {
                    $html .= sprintf(
                        '<span class="correct-word" aria-label="Resposta correta: %s">%s</span>',
                        htmlspecialchars($result['correct_word'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                        htmlspecialchars($result['correct_word'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    );
                }

                $html .= '</span>';
                $html .= $pa;
            } else {
                $word  = htmlspecialchars($token['word'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html .= $pb . $word . $pa;
            }

            $html .= ' ';
        }

        return $html;
    }

    // -----------------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------------

    /**
     * Calcula a largura do campo em caracteres (5-48ch).
     */
    private function fieldWidth(string $word): int
    {
        $len = function_exists('mb_strlen') ? mb_strlen($word, 'UTF-8') : strlen($word);
        return max(self::WIDTH_MIN, min(self::WIDTH_MAX, $len + 2));
    }

    /**
     * Gera um bloco de erro acessível.
     */
    private function errorBlock(string $message): string
    {
        return sprintf(
            '<div role="alert" aria-live="assertive" aria-atomic="true"'
            . ' class="rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">%s</div>',
            htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }
}
