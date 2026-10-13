# Requirements Document

## Introduction

O **Music Fill-in-the-Blank** é uma aplicação web PHP pura para prática de idiomas por meio de letras de música. O usuário cola a letra de uma música, o sistema seleciona palavras aleatoriamente e as substitui por campos em branco. Ao submeter, cada resposta é avaliada e destacada visualmente conforme o grau de acerto. A interface é em português, minimalista e clean, sem emojis ou gradientes.

---

## Glossary

- **Sistema**: a aplicação web PHP Music Fill-in-the-Blank.
- **Letra**: o texto completo da música colado pelo usuário.
- **Token**: cada palavra individual extraída da Letra, preservando pontuação adjacente.
- **Lacuna**: um Token selecionado para ser ocultado e substituído por um campo de entrada.
- **Campo**: o elemento HTML `<input type="text">` que representa uma Lacuna na tela.
- **Gabarito**: o conjunto de Tokens originais correspondentes às Lacunas geradas.
- **Submissão**: o ato do usuário enviar as respostas preenchidas nos Campos para avaliação.
- **Distância de Edição**: a distância de Levenshtein entre dois strings, ignorando diferença de maiúsculas/minúsculas.
- **Avaliação**: a classificação de cada resposta em Correta, Próxima ou Incorreta.
- **Avaliador**: componente PHP responsável por calcular a Avaliação de cada Campo.
- **Seletor**: componente PHP responsável por escolher quais Tokens se tornam Lacunas.
- **Renderizador**: componente PHP responsável por gerar o HTML do exercício a partir da Letra e das Lacunas.

---

## Requirements

### Requisito 1: Entrada da Letra

**User Story:** Como estudante de idiomas, quero colar a letra de uma música em um campo de texto, para que o sistema gere o exercício de preenchimento de lacunas.

#### Critérios de Aceitação

1. THE Sistema SHALL exibir um formulário com um campo de texto multilinha para o usuário inserir a Letra, com capacidade máxima de 10.000 caracteres.
2. THE Sistema SHALL exibir um botão de envio rotulado "Gerar exercício" no formulário de entrada.
3. WHEN o usuário submete o formulário de entrada com a Letra vazia, THE Sistema SHALL exibir uma mensagem de erro indicando que o campo está vazio sem gerar o exercício.
4. WHEN o usuário submete o formulário de entrada com a Letra contendo menos de 10 Tokens, THE Sistema SHALL exibir uma mensagem de erro indicando que a letra não possui palavras suficientes, sem gerar o exercício.
5. WHEN o usuário submete o formulário de entrada com a Letra excedendo 10.000 caracteres, THE Sistema SHALL exibir uma mensagem de erro indicando que o texto é longo demais, sem gerar o exercício.
6. WHEN o usuário submete o formulário de entrada com uma Letra válida, THE Sistema SHALL processar a Letra e exibir o exercício gerado em até 30 segundos.
7. IF o processamento da Letra exceder 30 segundos ou falhar, THEN THE Sistema SHALL exibir uma mensagem de erro indicando falha no processamento e SHALL manter o campo de texto preenchido com o conteúdo inserido.

---

### Requisito 2: Seleção de Lacunas

**User Story:** Como estudante de idiomas, quero que o sistema escolha palavras aleatórias da letra para se tornarem lacunas, para que eu possa praticar vocabulário de forma variada.

#### Critérios de Aceitação

1. WHEN o Sistema processa uma Letra válida, THE Seletor SHALL selecionar entre 20% e 30% dos Tokens elegíveis como Lacunas, arredondado para o inteiro mais próximo, com mínimo de 3 Lacunas.
2. WHEN o Sistema processa uma Letra válida, THE Seletor SHALL excluir da seleção Tokens com 2 caracteres ou menos.
3. WHEN o Sistema processa uma Letra válida, THE Seletor SHALL excluir da seleção Tokens compostos exclusivamente por caracteres de pontuação.
4. WHEN o Sistema processa a mesma Letra em requisições distintas, THE Seletor SHALL produzir conjuntos de Lacunas diferentes em pelo menos 90% dos casos, mediante uso de semente aleatória baseada no tempo.
5. IF o número de Tokens elegíveis for menor que 3, THEN THE Sistema SHALL exibir uma mensagem de erro indicando que a Letra não possui palavras elegíveis suficientes para gerar o exercício.
6. WHEN o Seletor distribui as Lacunas, THE Seletor SHALL garantir que não haja mais de 3 Lacunas consecutivas na sequência de Tokens da Letra.

---

### Requisito 3: Renderização do Exercício

**User Story:** Como estudante de idiomas, quero ver a letra com campos em branco no lugar das palavras ocultadas, para que eu possa preencher as respostas mantendo o contexto visual da letra.

#### Critérios de Aceitação

1. WHEN o exercício é gerado, THE Renderizador SHALL exibir a Letra com cada Lacuna substituída por um Campo de entrada de texto.
2. WHEN o exercício é gerado, THE Renderizador SHALL dimensionar cada Campo com largura proporcional ao número de caracteres do Token correspondente, com mínimo de 3 e máximo de 40 caracteres de largura visual.
3. WHEN o exercício é gerado, THE Renderizador SHALL preservar as quebras de linha originais da Letra na exibição, mantendo cada verso em uma linha separada.
4. WHEN o exercício é gerado, THE Renderizador SHALL preservar a pontuação adjacente a cada Token fora do Campo, posicionada imediatamente antes ou após o Campo sem espaço extra.
5. WHEN o exercício é gerado, THE Renderizador SHALL incluir um botão rotulado "Verificar respostas" para submeter a Submissão, permanecendo visível e interativo enquanto pelo menos um Campo estiver sem preenchimento.
6. WHEN o exercício é gerado, THE Renderizador SHALL ocultar o Gabarito do código-fonte HTML visível ao usuário, de modo que nenhuma palavra ocultada apareça em texto legível no HTML renderizado.
7. IF o exercício contiver zero Lacunas, THEN THE Renderizador SHALL exibir uma mensagem de erro indicando que nenhuma palavra foi ocultada e não SHALL exibir o botão "Verificar respostas".
8. IF a Letra estiver vazia ou ausente, THEN THE Renderizador SHALL exibir uma mensagem de erro indicando que o conteúdo da letra é inválido.

---

### Requisito 4: Avaliação das Respostas

**User Story:** Como estudante de idiomas, quero receber feedback visual imediato ao submeter minhas respostas, para que eu saiba quais palavras acertei, errei por pouco ou errei completamente.

#### Critérios de Aceitação

1. WHEN o usuário realiza a Submissão, THE Avaliador SHALL comparar cada Campo com o Token de mesma posição no Gabarito, ignorando diferença de maiúsculas/minúsculas, sendo que o número de Campos deve ser igual ao número de Tokens do Gabarito.
2. IF a Distância de Edição entre a resposta e o Token for igual a 0, THEN THE Avaliador SHALL classificar a resposta como Correta.
3. IF a Distância de Edição entre a resposta e o Token for maior que 0, menor ou igual a 2, e o Token tiver comprimento maior ou igual a 4 caracteres, THEN THE Avaliador SHALL classificar a resposta como Próxima.
4. IF a Distância de Edição entre a resposta e o Token for maior que 2, ou o Token tiver comprimento menor que 4 caracteres e a Distância de Edição for maior que 0, THEN THE Avaliador SHALL classificar a resposta como Incorreta.
5. WHEN a Submissão é avaliada, THE Renderizador SHALL contornar cada Campo com borda verde para respostas Corretas, amarela para respostas Próximas e vermelha para respostas Incorretas.
6. WHEN a Submissão é avaliada, THE Renderizador SHALL exibir o Token correto abaixo de cada Campo classificado como Incorreto ou Próximo.
7. WHEN a Submissão é avaliada, THE Sistema SHALL exibir um resumo com o total de respostas Corretas, Próximas e Incorretas.
8. IF o Campo estiver vazio no momento da Submissão, THEN THE Avaliador SHALL classificar a resposta como Incorreta sem calcular a Distância de Edição.
9. IF o número de Campos for diferente do número de Tokens do Gabarito no momento da Submissão, THEN THE Avaliador SHALL rejeitar a Submissão e exibir uma mensagem de erro indicando incompatibilidade entre campos e gabarito.

---

### Requisito 5: Reinício do Exercício

**User Story:** Como estudante de idiomas, quero poder reiniciar o exercício com a mesma letra ou com uma nova, para que eu possa praticar quantas vezes quiser.

#### Critérios de Aceitação

1. WHEN a Submissão é avaliada, THE Sistema SHALL exibir um botão rotulado "Tentar novamente" que gera um novo exercício com a mesma Letra e novas Lacunas aleatórias.
2. WHEN a Submissão é avaliada, THE Sistema SHALL exibir um botão rotulado "Nova letra" que retorna o usuário ao formulário de entrada com todos os campos vazios.
3. WHEN o usuário aciona "Tentar novamente", THE Seletor SHALL produzir um conjunto de índices de Lacunas diferente do conjunto da tentativa anterior, realizando até 10 sorteios para obter um conjunto distinto; se após 10 sorteios nenhum conjunto distinto for encontrado, THE Seletor SHALL utilizar o conjunto sorteado na décima tentativa.

---

### Requisito 6: Interface e Acessibilidade

**User Story:** Como estudante de idiomas, quero uma interface limpa e acessível, para que eu possa usar o exercício sem distração e com leitores de tela.

#### Critérios de Aceitação

1. THE Sistema SHALL exibir toda a interface em português do Brasil.
2. THE Sistema SHALL aplicar um design minimalista sem gradientes de cor, sem emojis e sem traços longos tipográficos (—) na interface gerada.
3. THE Sistema SHALL utilizar ícones vetoriais (via Font Awesome ou Heroicons inline SVG) quando representações visuais forem necessárias, em substituição a emojis.
4. THE Sistema SHALL associar cada Campo a um atributo `aria-label` descrevendo a posição da Lacuna na Letra no formato "Lacuna N", onde N é o número ordinal da Lacuna dentro do exercício iniciando em 1.
5. THE Sistema SHALL garantir que a navegação entre Campos seja possível apenas com o teclado, por meio da tecla Tab, seguindo a ordem visual dos Campos de cima para baixo e da esquerda para a direita.
6. WHEN o usuário pressiona a tecla Enter em qualquer Campo durante o exercício, THE Sistema SHALL submeter a Submissão com os valores preenchidos em todos os Campos no momento do acionamento.
7. THE Sistema SHALL exibir mensagens de erro em uma região com atributo `role="alert"` para que leitores de tela anunciem o conteúdo automaticamente ao ser inserido no DOM.
8. IF o número total de versos da Letra for menor que o número de Lacunas solicitado, THEN THE Sistema SHALL exibir uma mensagem de erro indicando que a Letra não possui versos suficientes e SHALL manter o formulário de entrada preenchido com os dados inseridos pelo usuário.
