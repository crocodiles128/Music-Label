<?php
declare(strict_types=1);

/**
 * Tokenizer
 *
 * Extrai tokens da letra preservando pontuação adjacente e quebras de linha.
 *
 * Cada token é um array associativo com as chaves:
 *   - 'word'         string  A palavra (letras, dígitos e hífens internos); vazia em tokens de newline
 *   - 'punct_before' string  Pontuação imediatamente antes da palavra (ex.: abertura de aspas)
 *   - 'punct_after'  string  Pontuação imediatamente após a palavra (ex.: vírgula, ponto final)
 *   - 'is_newline'   bool    true se o token representa uma quebra de linha
 *   - 'eligible'     bool    true se o token pode se tornar uma lacuna
 *
 * Critérios de elegibilidade (Req. 2.2 e 2.3):
 *   - Não é newline
 *   - word possui pelo menos 3 caracteres (> 2)
 *   - word contém pelo menos uma letra Unicode (não é pontuação pura)
 */
class Tokenizer
{
    /**
     * Expressão que captura um "chunk" da letra.
     * Grupos:
     *   1 - sequência de quebras de linha (\r\n ou \n ou \r)
     *   2 - pontuação antes da palavra (qualquer \pP ou \pS antes da palavra)
     *   3 - a palavra em si: letras/dígitos Unicode + hífens internos
     *   4 - pontuação depois da palavra
     */
    private const PATTERN = '/(\r\n|\r|\n)|([\pP\pS]*)((?:\pL|\pN)(?:[\pL\pN]|(?<=[\pL\pN])-(?=[\pL\pN]))*)([\pP\pS]*)/u';

    /**
     * @param  string  $lyrics  Letra completa da música
     * @return array<int, array{
     *     word: string,
     *     punct_before: string,
     *     punct_after: string,
     *     is_newline: bool,
     *     eligible: bool
     * }>
     */
    public function tokenize(string $lyrics): array
    {
        // Normaliza fim de linha para \n e remove espaços no início/fim
        $lyrics = trim($lyrics);
        $lyrics = str_replace(["\r\n", "\r"], "\n", $lyrics);

        $tokens = [];

        if (preg_match_all(self::PATTERN, $lyrics, $matches, PREG_SET_ORDER) === false) {
            return $tokens;
        }

        foreach ($matches as $m) {
            // Grupo 1 preenchido => token de quebra de linha
            if ($m[1] !== '') {
                $tokens[] = [
                    'word'         => '',
                    'punct_before' => '',
                    'punct_after'  => '',
                    'is_newline'   => true,
                    'eligible'     => false,
                ];
                continue;
            }

            $word        = $m[3] ?? '';
            $punctBefore = $m[2] ?? '';
            $punctAfter  = $m[4] ?? '';

            // Descarta matches sem palavra (grupos 2/4 sozinhos não devem ocorrer,
            // mas defendemos contra isso)
            if ($word === '') {
                continue;
            }

            $tokens[] = [
                'word'         => $word,
                'punct_before' => $punctBefore,
                'punct_after'  => $punctAfter,
                'is_newline'   => false,
                'eligible'     => $this->isEligible($word),
            ];
        }

        return $tokens;
    }

    /**
     * Verifica se uma palavra é elegível para se tornar lacuna.
     *
     * Req. 2.2: comprimento > 2 caracteres
     * Req. 2.3: contém pelo menos uma letra Unicode (não é pontuação/número puro)
     */
    private function isEligible(string $word): bool
    {
        // mb_strlen com fallback para strlen (mbstring pode estar desabilitada)
        $len = function_exists('mb_strlen') ? mb_strlen($word, 'UTF-8') : strlen($word);

        if ($len <= 2) {
            return false;
        }

        // Deve conter ao menos uma letra
        return (bool) preg_match('/\pL/u', $word);
    }
}
