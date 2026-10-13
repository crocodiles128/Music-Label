<?php
declare(strict_types=1);

session_start();

// ---------------------------------------------------------------------------
// Includes das classes (stubs por ora; implementadas nas Tasks seguintes)
// ---------------------------------------------------------------------------
require_once __DIR__ . '/src/Tokenizer.php';
require_once __DIR__ . '/src/Selector.php';
require_once __DIR__ . '/src/Evaluator.php';
require_once __DIR__ . '/src/Renderer.php';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Escapa saída para HTML de forma segura. */
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Contagem simples de tokens com pelo menos uma letra (pré-validação rápida).
 * Usada apenas para a verificação de "mínimo 10 palavras" antes de acionar o Tokenizer.
 */
function countWordTokens(string $text): int
{
    $parts = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        return 0;
    }
    $words = array_filter($parts, static fn(string $t): bool => (bool) preg_match('/\pL/u', $t));
    return count($words);
}

/**
 * Processa a letra: tokeniza, seleciona lacunas e persiste na sessão.
 * Retorna uma string de erro vazia em caso de sucesso, ou a mensagem de erro.
 *
 * Chaves salvas em $_SESSION:
 *   letra_original    string        Texto completo da letra
 *   tokens            array         Array de tokens do Tokenizer
 *   indices           array<int>    Índices selecionados como lacunas
 *   indices_anteriores array<int>   Mesmos índices, guardados para o retry
 */
function processarLetra(string $letra, array $previousIndices = []): string
{
    try {
        $tokenizer = new Tokenizer();
        $tokens    = $tokenizer->tokenize($letra);

        $selector = new Selector();
        $indices  = $selector->select($tokens, $previousIndices);

        $_SESSION['letra_original']     = $letra;
        $_SESSION['tokens']             = $tokens;
        $_SESSION['indices']            = $indices;
        $_SESSION['indices_anteriores'] = $indices;

        return '';
    } catch (RuntimeException $e) {
        return $e->getMessage();
    } catch (Throwable $e) {
        return 'Falha no processamento da letra. Tente novamente.';
    }
}

// ---------------------------------------------------------------------------
// Roteamento
// ---------------------------------------------------------------------------
$tela  = $_GET['tela'] ?? 'entrada';
$erro  = '';
$letra = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ------------------------------------------------------------------
    // action=generate  →  valida letra, tokeniza, seleciona lacunas
    // ------------------------------------------------------------------
    if ($action === 'generate') {
        $letra = $_POST['letra'] ?? '';

        // Validações rápidas (Req. 1)
        if (trim($letra) === '') {
            $erro = 'O campo da letra não pode estar vazio.';

        } elseif ((function_exists('mb_strlen') ? mb_strlen($letra, 'UTF-8') : strlen($letra)) > 10000) {
            $erro = 'O texto é longo demais (máximo 10.000 caracteres).';

        } elseif (countWordTokens($letra) < 10) {
            $erro = 'A letra não possui palavras suficientes (mínimo 10 palavras).';
        }

        if ($erro === '') {
            // Processa: tokeniza + seleciona lacunas + salva sessão
            $erro = processarLetra($letra);
        }

        if ($erro === '') {
            // Sucesso: redireciona para a tela de exercício (PRG pattern)
            header('Location: /Music-Label/?tela=exercicio');
            exit;
        } else {
            // Mantém a tela de entrada com o textarea preenchido
            $tela = 'entrada';
        }

    // ------------------------------------------------------------------
    // action=check  →  avalia respostas (Req. 4)
    // ------------------------------------------------------------------
    } elseif ($action === 'check') {
        $sessTokens  = $_SESSION['tokens']  ?? [];
        $sessIndices = $_SESSION['indices'] ?? [];

        // Monta gabarito: palavras dos tokens que são lacunas, em ordem
        $gabarito = array_map(
            fn(int $i): string => $sessTokens[$i]['word'] ?? '',
            $sessIndices
        );

        $answers = array_values($_POST['resp'] ?? []);

        try {
            $evaluator = new Evaluator();
            $results   = $evaluator->evaluate($answers, $gabarito);

            // Persiste respostas e resultados para renderização na tela de resultado
            $_SESSION['results'] = $results;
            $_SESSION['answers'] = $answers;

            header('Location: /Music-Label/?tela=resultado');
            exit;

        } catch (InvalidArgumentException $e) {
            // Contagem incompatível — mostra erro na tela de exercício
            $erro = $e->getMessage();
            $tela = 'exercicio';
        } catch (Throwable $e) {
            $erro = 'Falha ao avaliar as respostas. Tente novamente.';
            $tela = 'exercicio';
        }

    // ------------------------------------------------------------------
    // action=retry  →  nova seleção de lacunas para a mesma letra (Req. 5.3)
    // ------------------------------------------------------------------
    } elseif ($action === 'retry') {
        $letraSalva      = $_SESSION['letra_original']     ?? '';
        $indicesAntigos  = $_SESSION['indices_anteriores'] ?? [];

        if ($letraSalva === '') {
            // Sessão expirou ou inválida — volta ao início
            header('Location: /Music-Label/');
            exit;
        }

        $erro = processarLetra($letraSalva, $indicesAntigos);

        if ($erro === '') {
            header('Location: /Music-Label/?tela=exercicio');
            exit;
        } else {
            // Falha inesperada — volta ao formulário com erro
            $letra = $letraSalva;
            $tela  = 'entrada';
        }

    // ------------------------------------------------------------------
    // action=new  →  nova letra, limpa sessão
    // ------------------------------------------------------------------
    } elseif ($action === 'new') {
        session_destroy();
        header('Location: /Music-Label/');
        exit;
    }
}

// ---------------------------------------------------------------------------
// Recupera letra para manter textarea preenchido em caso de erro na entrada
// (A variável $letra já é preenchida pelo bloco action=generate ou action=retry acima)
// ---------------------------------------------------------------------------

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Music Fill-in-the-Blank</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Overrides locais (inputs ch, feedback visual) -->
    <link rel="stylesheet" href="/Music-Label/css/app.css">
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">

<div class="max-w-2xl mx-auto px-4 py-10">

    <!-- Cabeçalho -->
    <header class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">
            Music Fill-in-the-Blank
        </h1>
        <p class="mt-1 text-sm text-gray-500">
            Cole a letra de uma musica e pratique vocabulario preenchendo as lacunas.
        </p>
    </header>

    <!-- ================================================================
         TELA 1: Formulário de entrada da letra
    ================================================================ -->
    <?php if ($tela === 'entrada'): ?>

        <!-- Região de alerta acessível: leitores de tela anunciam quando inserido -->
        <div role="alert" aria-live="assertive" aria-atomic="true"
             class="<?= $erro !== '' ? '' : 'hidden' ?> mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
            <?= h($erro) ?>
        </div>

        <form method="POST" action="/Music-Label/" novalidate>
            <input type="hidden" name="action" value="generate">

            <label for="letra" class="block text-sm font-medium text-gray-700 mb-1">
                Letra da música
            </label>

            <textarea
                id="letra"
                name="letra"
                maxlength="10000"
                rows="18"
                required
                class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm
                       leading-relaxed focus:border-gray-500 focus:outline-none
                       focus:ring-1 focus:ring-gray-400 resize-y"
                placeholder="Cole aqui a letra completa da música..."
                aria-describedby="letra-hint"
            ><?= h($letra) ?></textarea>

            <p id="letra-hint" class="mt-1 text-xs text-gray-400">
                Máximo 10.000 caracteres. O sistema selecionará palavras aleatórias para você preencher.
            </p>

            <div class="mt-4">
                <button
                    type="submit"
                    class="rounded bg-gray-800 px-5 py-2 text-sm font-medium text-white
                           hover:bg-gray-700 focus:outline-none focus:ring-2
                           focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                    Gerar exercício
                </button>
            </div>
        </form>

    <!-- ================================================================
         TELA 2: Exercício (Renderer implementado na Task 5)
    ================================================================ -->
    <?php elseif ($tela === 'exercicio'): ?>

        <?php
        $sessTokens  = $_SESSION['tokens']  ?? [];
        $sessIndices = $_SESSION['indices'] ?? [];
        $sessLetra   = $_SESSION['letra_original'] ?? '';

        if (empty($sessTokens) || empty($sessIndices) || $sessLetra === ''):
        ?>
            <div role="alert" aria-live="assertive" aria-atomic="true"
                 class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                Sessão expirada ou inválida. Por favor, cole a letra novamente.
            </div>
            <a href="/Music-Label/"
               class="text-sm text-gray-600 underline hover:text-gray-800">
                Voltar ao início
            </a>
        <?php else:
            $renderer     = new Renderer();
            $exerciseHtml = $renderer->renderExercise($sessTokens, $sessIndices);
            $gapCount     = count($sessIndices);
        ?>
            <!-- Erro de avaliação (ex: incompatibilidade de campos) -->
            <?php if ($erro !== ''): ?>
            <div role="alert" aria-live="assertive" aria-atomic="true"
                 class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= h($erro) ?>
            </div>
            <?php endif; ?>
            <form method="POST" action="/Music-Label/" id="exercise-form" novalidate>
                <input type="hidden" name="action" value="check">

                <!-- Área da letra com lacunas -->
                <div class="lyrics-body rounded border border-gray-200 bg-white px-6 py-5 text-sm leading-loose">
                    <?= $exerciseHtml ?>
                </div>

                <!-- Info de lacunas + botão Verificar -->
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-gray-400">
                        <?= $gapCount ?> lacuna<?= $gapCount !== 1 ? 's' : '' ?> para preencher.
                        Use Tab para navegar entre os campos. Enter para verificar.
                    </p>
                    <button
                        type="submit"
                        class="rounded bg-gray-800 px-5 py-2 text-sm font-medium text-white
                               hover:bg-gray-700 focus:outline-none focus:ring-2
                               focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                        Verificar respostas
                    </button>
                </div>
            </form>

            <!-- Botão Nova letra fora do formulário do exercício -->
            <div class="mt-3">
                <form method="POST" action="/Music-Label/">
                    <input type="hidden" name="action" value="new">
                    <button type="submit"
                            class="text-sm text-gray-500 underline hover:text-gray-700
                                   focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
                        Nova letra
                    </button>
                </form>
            </div>

            <!-- Enter em qualquer campo submete o formulário (Req. 6.6) -->
            <script>
            document.getElementById('exercise-form').addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.matches('input[type="text"]')) {
                    e.preventDefault();
                    this.submit();
                }
            });
            </script>
        <?php endif; ?>

    <!-- ================================================================
         TELA 3: Resultado / feedback (Req. 4 e 5)
    ================================================================ -->
    <?php elseif ($tela === 'resultado'): ?>

        <?php
        $sessTokens  = $_SESSION['tokens']   ?? [];
        $sessIndices = $_SESSION['indices']  ?? [];
        $sessResults = $_SESSION['results']  ?? [];
        $sessAnswers = $_SESSION['answers']  ?? [];

        if (empty($sessTokens) || empty($sessIndices) || empty($sessResults)):
        ?>
            <div role="alert" aria-live="assertive" aria-atomic="true"
                 class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                Sessão expirada ou resultado inválido. Por favor, comece novamente.
            </div>
            <a href="/Music-Label/"
               class="text-sm text-gray-600 underline hover:text-gray-800">Voltar ao início</a>
        <?php else:
            // Resumo (Req. 4.7)
            $countCorrect = count(array_filter($sessResults, fn($r) => $r['status'] === 'correct'));
            $countClose   = count(array_filter($sessResults, fn($r) => $r['status'] === 'close'));
            $countWrong   = count(array_filter($sessResults, fn($r) => $r['status'] === 'wrong'));
            $total        = count($sessResults);

            $renderer    = new Renderer();
            $resultHtml  = $renderer->renderResult($sessTokens, $sessIndices, $sessResults, $sessAnswers);
        ?>

            <!-- Resumo de resultados (Req. 4.7) -->
            <div class="mb-5 rounded border border-gray-200 bg-white px-5 py-4">
                <p class="text-sm font-medium text-gray-800 mb-3">Resultado</p>
                <div class="flex flex-wrap gap-4 text-sm">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded-full bg-green-500"></span>
                        <span class="text-gray-700"><strong><?= $countCorrect ?></strong> correta<?= $countCorrect !== 1 ? 's' : '' ?></span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded-full bg-yellow-400"></span>
                        <span class="text-gray-700"><strong><?= $countClose ?></strong> próxima<?= $countClose !== 1 ? 's' : '' ?></span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded-full bg-red-500"></span>
                        <span class="text-gray-700"><strong><?= $countWrong ?></strong> incorreta<?= $countWrong !== 1 ? 's' : '' ?></span>
                    </span>
                </div>
            </div>

            <!-- Letra com feedback visual -->
            <div class="lyrics-body rounded border border-gray-200 bg-white px-6 py-5 text-sm leading-loose">
                <?= $resultHtml ?>
            </div>

            <!-- Botões de navegação (Req. 5.1 e 5.2) -->
            <div class="mt-5 flex flex-wrap gap-3">
                <form method="POST" action="/Music-Label/">
                    <input type="hidden" name="action" value="retry">
                    <button type="submit"
                            class="rounded bg-gray-800 px-5 py-2 text-sm font-medium text-white
                                   hover:bg-gray-700 focus:outline-none focus:ring-2
                                   focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                        Tentar novamente
                    </button>
                </form>
                <form method="POST" action="/Music-Label/">
                    <input type="hidden" name="action" value="new">
                    <button type="submit"
                            class="rounded border border-gray-300 px-5 py-2 text-sm font-medium
                                   text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2
                                   focus:ring-gray-400 focus:ring-offset-2 transition-colors">
                        Nova letra
                    </button>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

</body>
</html>
