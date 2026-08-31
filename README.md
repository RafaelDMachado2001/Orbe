# Orbe

App de controle financeiro pessoal/PJ. **Laravel 12 como API** + **React 18 + TypeScript** como SPA, **PostgreSQL** como banco, tudo orquestrado por **Docker Compose**.

O backend é o motor: autenticação, validação, cálculo de saldo, distribuição de parcelas, fechamento de fatura, agregações mensais e projeção acontecem na API. O frontend recebe os dados prontos e desenha — não há regra de negócio no React.

---

## Subir o projeto

```bash
make setup          # copia o .env, constrói as imagens e sobe tudo
```

| Serviço | Endereço |
|---|---|
| SPA React | http://localhost:5173 |
| API | http://localhost:8000/api/v1 |
| Health check | http://localhost:8000/api/v1/health |
| PostgreSQL | localhost:5433 |
| Redis | localhost:6380 |

No primeiro boot o container `api` roda as migrations e popula a base de demonstração (`DB_SEED_ON_BOOT=true`).

**Conta de demonstração:** `demo@versofinancas.app` / `verso1234`

Se preferir começar do zero, `make fresh` recria a base e roda o seeder novamente.

### Comandos

```bash
make up        # sobe os serviços
make down      # derruba (mantém os volumes)
make logs      # segue os logs
make shell     # bash no container da API
make fresh     # recria a base + seeder
make test      # suíte Pest
make pint      # formata o PHP
make stan      # PHPStan nível 6
make tsc       # checagem de tipos do frontend
make lint      # ESLint
make check     # toda a esteira de qualidade
```

---

## Arquitetura

```
.
├── backend/          Laravel 12 — API REST em /api/v1
├── frontend/         React 18 + TypeScript + Vite
├── docker/           Dockerfiles, nginx, entrypoints
├── docker-compose.yml
└── Makefile
```

### Backend

```
app/
├── Domain/<Contexto>/{Actions,DTOs,Enums,Models,Queries}
│   ├── Banking/      bancos e contas
│   ├── Cards/        cartões, faturas e parcelas
│   ├── Ledger/       categorias, lançamentos e recorrências
│   ├── Planning/     orçamentos e metas
│   ├── Import/       leitura de extrato OFX/CSV e gravação do lote
│   ├── Dashboard/    montagem da visão geral
│   ├── Forecast/     serviço de projeção
│   └── Identity/     cadastro de usuário
├── Console/Commands/ tarefas do agendador (invoices:close-due)
├── Http/{Controllers/Api/V1,Requests,Resources,Middleware}
└── Support/          trait de escopo por usuário, Money, UserScope
```

**Controllers finos.** Validam via Form Request, delegam para uma Action ou Query e devolvem um API Resource. Nenhuma regra de negócio vive no controller.

**Actions e Queries.** Regra de escrita mora em `Actions` (`RegisterCardPurchaseAction`, `PayInvoiceAction`, `TransferBetweenAccountsAction`); regra de leitura mora em `Queries` (`MonthlyTotalsQuery`, `CategoryBreakdownQuery`, `CardsOverviewQuery`). Cada regra existe em um lugar só.

**DTOs `readonly`** com constructor property promotion validam o próprio conteúdo no construtor — um `TransactionData` com valor negativo não chega a existir.

**Enums nativos** com backing `string` para todo status e tipo, sempre com `match` no lugar de `switch`.

**Sem N+1.** `Model::preventLazyLoading()` está ligado fora de produção, então um acesso não eager-loaded quebra em desenvolvimento. As agregações mensais são feitas em uma query com `selectRaw` + `groupBy`, nunca somando em PHP.

**Isolamento por usuário.** A trait `BelongsToUser` aplica um global scope que restringe toda consulta ao usuário autenticado e preenche o `user_id` na criação. Sem usuário autenticado (filas, seeders, console) o escopo não é aplicado e o chamador delimita explicitamente com `ownedBy()`.

**Regra de negócio recusada vira 422.** Toda exceção que herda de
`App\Support\Exceptions\DomainRuleException` é traduzida na borda HTTP com a
própria mensagem. A regra é escrita uma vez, no domínio, e a interface apenas
a exibe — não há lista de mensagens duplicada no React.

**Dinheiro nunca em `float` solto.** Colunas são `decimal(15,2)` com cast `decimal:2`; somas sensíveis passam por `App\Support\Money`, que trabalha em centavos inteiros. `Money::split()` distribui o resíduo da divisão nas primeiras parcelas, de forma que 10 parcelas de R$ 100,00 nunca somam R$ 99,99.

### Decisões de modelagem

**`transactions.direction` e a coluna gerada `signed_amount`.** Toda transação carrega a direção do dinheiro (`entrada`/`saida`). O banco deriva `signed_amount = amount * (±1)` como coluna gerada, então saldo de conta é uma única soma indexada, sem `CASE` espalhado por várias queries.

**Compra de cartão = transação-mãe + N parcelas.** A compra vira uma `transaction` marcada com `is_installment_parent` e N registros em `installments`, distribuídos nas faturas conforme o dia de fechamento do cartão. As **parcelas** são a unidade de relatório; a transação-mãe fica fora das agregações. Isso vale igualmente para compras à vista (1 parcela), o que dá uma regra única para todo gasto no crédito. Essa regra vive num lugar só: `MonthlyTotalsQuery`.

**Parcelamento em conta = N lançamentos, um por mês.** Uma despesa parcelada ou
um empréstimo **não** reaproveitam a tabela `installments`: aquela modelagem
existe porque a parcela de cartão não é um lançamento de conta — ela nasce
dentro de uma fatura e só vira dinheiro quando a fatura é paga. Fora do cartão a
parcela sai da conta no próprio mês, então cada uma é uma `transaction` por
direito, na data do seu mês, costuradas por um `installment_group_id`. É o que
faz a previsibilidade funcionar sem nenhuma query nova: a parcela de novembro já
nasce no extrato de novembro, já soma no total daquele mês e já aparece na
projeção.

**Empréstimo é uma aba, não uma categoria.** `EntryKind::Emprestimo` grava as
prestações como despesa em conta com `is_loan` e `lender`, e marca a categoria
de sistema `emprestimos`. O dinheiro **recebido** não é lançado: inflaria a
receita do mês e distorceria a média de 6 meses que alimenta a previsão. Quem
quiser registrá-lo lança uma receita à parte.

**Pagamento de fatura é `transferencia`, não despesa.** As compras já foram contabilizadas como despesa no mês de competência de cada parcela. Lançar o pagamento como despesa dobraria o gasto. Ele debita a conta (afeta saldo) e fica fora dos relatórios de resultado.

**Transferência entre contas gera um par espelhado** (saída na origem, entrada no destino) apontando um para o outro. Move saldo, mas não é receita nem despesa: o dinheiro não entrou nem saiu do patrimônio.

**Previsto não altera saldo.** Só `status = confirmado` entra no saldo atual. O previsto alimenta a projeção.

**Limite de cartão usado** = soma das parcelas ainda não pagas. O limite volta a ficar livre conforme as faturas são quitadas.

### A tela de Lançamentos

**Uma lista, duas fontes.** O extrato une lançamentos de conta e parcelas de
cartão em um `UNION ALL` no banco; ordenação, paginação e totais rodam por cima
dele. A compra-mãe fica de fora — quem representa o gasto é a parcela, com o
rótulo `3/10` e o valor da parcela. Paginar em PHP exigiria carregar o extrato
inteiro na memória a cada troca de filtro.

**Totais são do período, não da página.** Quem lê "saídas do período" quer o
período. Eles saem de uma agregação própria que ignora cancelados (não
movimentaram dinheiro) e transferências (mudaram de conta, não de patrimônio).

**Cinco caminhos de escrita, um controller.** `EntryKind` (receita, despesa,
cartão, empréstimo, transferência) é a aba do formulário e escolhe a Action. A
edição não aceita `kind`: ele vem do próprio registro, porque trocar a natureza
de um lançamento gravado mexeria nas parcelas, no par espelhado e na fatura de
uma vez.

**O valor pode ser o total ou o da prestação.** Uma compra parcelada é anunciada
pelo total ("R$ 3.600 em 12x"), um empréstimo pela prestação ("24 parcelas de
R$ 480"). O `amount_mode` diz qual dos dois chegou, e a multiplicação acontece na
fronteira (`WriteTransactionRequest`) — o domínio conhece um significado só.
Obrigar a pessoa a multiplicar de cabeça era o caminho mais curto para um centavo
errado no meio do parcelamento.

**Parcela futura nasce prevista.** O status escolhido no formulário vale para as
parcelas já vencidas; as futuras entram como `previsto`. Confirmar as 12 de uma
vez tiraria da conta, hoje, dinheiro que só sai ao longo de um ano. Editar o
plano redistribui valores e datas preservando o status das parcelas futuras que
alguém já tenha confirmado à mão.

**Excluir uma parcela leva o plano.** Manter "4 de 12" sozinha no extrato seria
pior que apagar demais — a mesma regra da compra no cartão.

**Editar compra de cartão redistribui as parcelas.** A distribuição vive na
`SyncInstallmentsAction`, compartilhada entre registrar e editar. Ela recalcula
tanto as faturas que recebem parcelas quanto as que perdem — senão editar de 3x
para 2x deixaria a fatura do terceiro mês inflada.

**O que a tela não deixa mexer.** Uma parcela de fatura já paga e a baixa de
fatura aparecem na lista, mas não se editam nem se excluem por aqui: alterá-las
faria a fatura paga divergir do que foi pago. As guardas vivem nas Actions
(`TransactionNotEditableException` → 422) e a interface as antecipa, explicando
no menu em vez de esconder a opção.

### A tela de Cartões

**Uma cascata de três níveis:** cartão → fatura → parcelas que a compõem. O
painel do topo soma apenas cartões ativos, porque limite de cartão arquivado
não está disponível para gastar; a dívida que ficou continua no "em aberto".

**O limite usado não se recalcula aqui.** Ele e a escolha de qual fatura
mostrar saem da `CardsOverviewQuery`, a mesma que alimenta a Visão geral —
duas telas exibindo o mesmo número precisam calculá-lo no mesmo lugar.

**Desfazer pagamento remove um por vez.** Uma fatura pode ter recebido
pagamentos parciais em datas diferentes; apagar os três porque o terceiro foi
engano tiraria dinheiro que de fato saiu da conta. Desfazer devolve o saldo à
conta, o valor ao saldo devedor e o limite ao cartão.

**Fatura de mês passado fecha sozinha.** Uma fatura de três meses atrás aparecer
como "aberta" é uma mentira do sistema, não um estado do mundo: o cartão fechou
aquele mês no dia dele, tenha alguém clicado ou não. A `CloseDueInvoicesAction`
faz a manutenção por dois caminhos — o comando agendado `invoices:close-due`
cuida da base inteira uma vez por dia, e uma varredura preguiçosa nas telas de
cartão e na Visão geral cuida de quem abriu o app onde o agendador nunca subiu.
As duas são idempotentes e custam uma query indexada que quase sempre não
devolve nada.

**Fechar tem duas portas.** `CloseInvoiceAction::handle()` é idempotente e serve
à automação. `handleOrFail()` serve ao botão da tela, onde silêncio seria pior
que erro. Como o fechamento retroativo é convenção, o único fechamento manual
que sobrou é **antecipar**: fechar a fatura de setembro no dia 5, com corte
previsto para o dia 20, é dizer "para mim setembro acabou" — e a compra do dia 10
passa a cair em outubro.

**Fatura vencida continua aceitando lançamento retroativo.** O desvio acima só
vale para fatura fechada antes da hora. Se a data de corte realmente passou, a
compra esquecida de março ainda entra na fatura de março; do contrário
corrigir o histórico seria impossível.

**O cartão é desenhado como cartão.** A miniatura usa a proporção 1,586 do
cartão físico (ISO/IEC 7810 ID-1), com chip, ondas de aproximação e a bandeira
em SVG — nenhuma imagem externa para o app buscar. Ela não é enfeite: é o que
faz o usuário achar o cartão dele na lista pela cor e pela bandeira, antes de
ler o apelido. A cor do cadastro define só o matiz; a luminosidade é fixa e
baixa, senão um cartão claro deixaria o número ilegível.

**O que a tela oferece vem da API.** `can_pay`, `can_close` e `can_undo` são
calculados no backend, não copiados para o React — do contrário a interface
ofereceria um botão que o domínio recusaria.

**Cartão com histórico não se exclui.** O cascade levaria compras, parcelas e
faturas junto, e o extrato perderia meses sem que ninguém pedisse. Excluir só
passa em cartão sem nenhuma compra; para o resto, arquivar.

**Mudar o ciclo não remaneja compras antigas.** Alterar fechamento ou
vencimento recalcula as datas das faturas ainda em aberto, mas não move
parcelas já alocadas: elas cairiam em outra fatura e o histórico deixaria de
bater com o que o banco cobrou.

### A tela de Despesas fixas

**A regra e o lançamento são coisas distintas.** A regra descreve o que se
repete e alimenta a projeção; lançar é o ato de escrever aquele mês no extrato.
Cadastrar não lança nada — coerente com o escopo de controle manual.

**Uma linha por ocorrência, não por mês.** Uma regra semanal gera quatro ou
cinco lançamentos no mês, cada um na sua data. O cálculo de ocorrências vive no
próprio `Recurrence`, junto de `isRunningOn()`, como o ciclo de fatura vive no
`CreditCard`.

**A contagem parte do início da regra, nunca do começo do mês.** "A cada 2
meses" precisa cair nos meses certos e a semanal precisa manter o dia da
semana. Para não percorrer o calendário dia a dia, o cursor salta de uma vez os
ciclos que cabem entre o início e o mês pedido.

**Não lança duas vezes a mesma data.** A verificação olha os lançamentos que já
existem para aquela regra naquele dia, e não o campo `last_materialized_on`: se
o lançamento gerado for excluído, ele precisa poder ser lançado de novo — um
carimbo de data não saberia disso. O campo segue preenchido como registro de
quando a regra rodou pela última vez.

**Ocorrência passada nasce confirmada; futura nasce prevista.** Só o confirmado
altera o saldo, então lançar o mês inteiro no dia 1º não pode adiantar dinheiro
que ainda não saiu.

**Regra no cartão vira compra de 1x na fatura**, não débito em conta: o dinheiro
só sai quando a fatura for paga.

**Editar não reescreve o passado.** Os meses já lançados foram pagos com o valor
da época; alterá-los retroativamente faria o saldo divergir do que aconteceu. A
mudança vale dos próximos lançamentos em diante.

**Excluir preserva o histórico.** A chave estrangeira é `nullOnDelete`: os
lançamentos permanecem no extrato e apenas perdem o selo de recorrente. Para
interromper sem perder a regra, existe pausar.

**Totais são o equivalente mensal.** Uma despesa semanal de R$ 100 entra como
R$ 433,33 no painel. Somar valor nominal de frequências diferentes daria um
número sem significado.

### A tela de Categorias

**A árvore tem dois níveis, e para.** "Casa › Aluguel" é útil; "Casa › Fixas ›
Moradia › Aluguel" é uma pasta dentro de outra que ninguém lembra de abrir, e
obrigaria todo relatório a decidir em qual nível agregar. A validação recusa o
terceiro nível e recusa subcategoria de tipo diferente da mãe — do contrário o
seletor de despesas passaria a oferecer uma categoria de receita.

**Excluir pergunta para onde, não "tem certeza?".** Apagar "Alimentação" com
trezentos lançamentos dentro deixaria trezentas linhas sem classificação e um mês
inteiro de relatório com um buraco. Categoria em uso só sai com destino, e a
mudança de lançamentos, despesas fixas e subcategorias acontece em uma transação
só. As subcategorias não são apagadas junto: vão para o destino, ou viram irmãs
dele quando o próprio destino já é uma subcategoria — nos dois casos a árvore
continua com dois níveis.

**Orçamento não é realocado.** Ele segue o cascade do banco e some com a
categoria: um teto de gasto é uma regra da categoria, não um dado do lançamento,
e mover o teto de "Lazer" para "Alimentação" criaria um limite que ninguém
definiu.

**Categoria de sistema se renomeia, não se exclui.** `is_system` diz que ela
nasceu com a conta; `system_key` diz *qual* ela é. A referência é pela chave, e
não pelo nome, porque o nome é do usuário e pode mudar — é assim que a aba de
empréstimo acha a categoria dele mesmo depois de rebatizada.

**O total ao lado de cada categoria é o do mês navegado**, somando conta e
cartão na mesma regra do resto do app. A mãe mostra também o total das filhas: é
o número que a pessoa procura quando cria "Casa" com "Aluguel" e "Condomínio"
dentro.

### A tela de Importar histórico

**Ler e gravar são duas requisições.** `POST /imports/preview` abre o arquivo e
devolve o que há dentro dele sem escrever uma linha; só o `POST /imports`
seguinte grava, com as linhas que sobraram marcadas. Um extrato traz dezenas de
lançamentos de uma vez — descobrir o engano depois custaria excluir um por um.

**O arquivo não fica no servidor.** Reler com outro mapeamento ou outro destino
reenvia o mesmo arquivo, que o navegador já tem em memória. Guardar um rascunho
de importação criaria um estado com dono e prazo de validade que ninguém pediu.

**Importar não é um caminho de escrita novo.** Conta reusa a
`RecordTransactionAction` e cartão reusa a `RegisterCardPurchaseAction`, com uma
parcela. Uma linha importada precisa produzir exatamente o mesmo registro que
digitar na tela produziria — inclusive a parcela e a fatura do ciclo certo —,
senão o extrato passaria a ter dois tipos de lançamento com regras diferentes.

**Repetido se reconhece por data, valor e direção**, não pela descrição: o banco
a reescreve entre um export e outro ("PIX ENVIADO" vira "Pix enviado - João") e o
usuário a edita depois de lançar à mão. O `FITID` do OFX seria mais exato, mas só
o extrato tem um — e a maior parte das repetições vem justamente de importar por
cima do que já foi digitado. Cada correspondência é consumida uma vez, então duas
compras iguais no mesmo dia contra uma já registrada marcam só a primeira.

**Crédito na fatura não vira compra.** Estorno, cashback e pagamento da fatura
anterior aparecem no OFX do cartão como valor positivo. Gravá-los como compra
negativa não existe no modelo e como compra positiva inflaria a fatura: eles
ficam de fora, com o motivo à vista, apontando a tela que sabe registrá-los.

**A sugestão de categoria é palpite, e assume isso.** O casamento é por palavra-
chave e aponta para o *nome* da categoria, não para um id — assim continua
funcionando depois de o usuário renomear ou recriar as categorias padrão, e cai
fora sozinho quando ela não existe. Errar custa um clique, e uma lista legível e
corrigível vale mais que um acerto que ninguém consegue auditar.

**OFX é lido por marcação, não por parser XML.** O padrão vive em duas
encarnações: 1.x é SGML, com as tags de valor sem fechamento, e 2.x é XML bem
formado. Um parser XML rejeitaria de cara a versão SGML — que é justamente a que
os bancos brasileiros entregam. Extrato de conta (`STMTRS`) e fatura de cartão
(`CCSTMTRS`) têm a mesma estrutura de lançamento e não precisam de leituras
separadas. Arquivo em Windows-1252 é convertido antes de qualquer leitura, senão
todo acento da descrição seria gravado corrompido.

**CSV é adivinhado e mostrado.** Separador, ordem das colunas e a forma do valor
(uma coluna com sinal ou duas separando débito de crédito) mudam de banco para
banco. O parser deduz tudo pelo cabeçalho — ou pelo conteúdo, quando não há
cabeçalho — e devolve o palpite junto com as linhas, para a tela exibi-lo e
deixar corrigir. Adivinhar em silêncio importaria o extrato trocado; pedir o
mapeamento antes de ler exigiria que o usuário abrisse a planilha.

**Desfazer é do lote inteiro.** `import_batches` guarda só o cabeçalho; quem
representa o dinheiro é a própria `transaction`, marcada com `import_batch_id`.
A conferência acontece antes de apagar qualquer coisa: se alguma compra do lote
já foi paga junto com a fatura, a operação toda é recusada — apagar o resto
deixaria a importação pela metade, sem ninguém saber o que sobrou.

### Serviço de previsão

`ForecastService` projeta N meses somando três fontes:

1. **Recorrências ativas** válidas naquele mês (com o número de ocorrências derivado da frequência);
2. **Parcelas futuras em aberto** — dívida já assumida no cartão;
3. **Média móvel de 6 meses** do gasto variável, ou seja, o histórico menos o que já é explicado por (1) e (2).

Retorna por mês receita e despesa previstas, saldo projetado acumulado, valor comprometido, sobra e um **score de confiança 0–100** derivado do coeficiente de variação do resultado histórico, que decai a cada mês de distância.

### Frontend

```
src/
├── app/         Providers, router, rota protegida, modo privacidade
├── components/  ui/ (primitivos) e layout/ (shell e sidebar)
├── features/    um diretório por feature (auth, dashboard, transactions,
│              cards, recurrences, imports)
├── lib/         api.ts (cliente axios), format.ts, cn.ts
└── types/api.ts fonte única dos tipos da API
```

- **TanStack Query** para dados do servidor; não há Redux nem estado global de dados.
- **React Hook Form + Zod** nos formulários, com os erros de validação da API mapeados campo a campo.
- **Recharts** com tema escuro; o mês projetado é desenhado com borda pontilhada roxa em vez de barra cheia.
- **Tailwind CSS 4**: os tokens da identidade visual vivem no bloco `@theme` de `src/styles.css` (equivalente ao `theme.extend` da v3) e viram utilitários — `bg-app`, `bg-surface`, `text-green`, `border-hairline`. Nenhum componente declara cor crua.
- **Toda formatação** (moeda, data, percentual) passa por `lib/format.ts`.
- **Estados obrigatórios** em cada tela: skeleton no carregamento (nunca spinner de página inteira), vazio com CTA, erro com retry.
- **Atualização no lugar, sem recarregar.** Trocar data, filtro ou página muda apenas o estado em memória; com `placeholderData: keepPreviousData` a lista e os totais anteriores continuam visíveis, esmaecidos, até a nova resposta chegar. Filtros não vão para a URL: navegar a cada clique encheria o histórico e faria o "voltar" desfazer filtros um a um. Toda escrita invalida as chaves de `transactions` **e** de `dashboard`, então a Visão geral reflete o lançamento novo sozinha.
- **Modo privacidade**: `Shift + H` borra todos os valores da tela.

---

## Autenticação

**Sanctum com token Bearer.** O login devolve um token, o frontend guarda no `localStorage` e o envia no header `Authorization`. A API é stateless, o que permite front e back em origens diferentes sem configuração de cookie cross-site.

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/api/v1/auth/register` | Cria a conta (já com o plano de categorias) e devolve o token |
| `POST` | `/api/v1/auth/login` | Autentica e devolve o token |
| `GET` | `/api/v1/auth/me` | Usuário autenticado |
| `POST` | `/api/v1/auth/logout` | Invalida o token usado |
| `GET` | `/api/v1/dashboard?month=2026-08` | Visão geral do mês |
| `GET` | `/api/v1/transactions` | Extrato paginado, com filtros e totais do período |
| `GET` | `/api/v1/transactions/options` | Contas, cartões, categorias e listas de enum para os seletores |
| `POST` | `/api/v1/transactions` | Registra receita, despesa, compra no cartão ou transferência |
| `GET` | `/api/v1/transactions/{id}` | O lançamento como o formulário de edição o vê |
| `PUT` | `/api/v1/transactions/{id}` | Edita o lançamento inteiro |
| `PATCH` | `/api/v1/transactions/{id}/status` | Confirma, volta para previsto ou cancela |
| `DELETE` | `/api/v1/transactions/{id}` | Exclui o lançamento |
| `GET` | `/api/v1/cards?month=2026-08&archived=1` | Painel de limites e lista de cartões |
| `GET` | `/api/v1/cards/options` | Bancos, contas e bandeiras para o formulário |
| `POST` `PUT` | `/api/v1/cards[/{id}]` | Cadastra e edita cartão |
| `PATCH` | `/api/v1/cards/{id}/archive` | Arquiva ou reativa |
| `DELETE` | `/api/v1/cards/{id}` | Exclui — só cartão sem nenhuma compra |
| `GET` | `/api/v1/cards/{id}/invoices` | Linha do tempo de faturas do cartão |
| `GET` | `/api/v1/invoices/{id}` | Fatura com as parcelas que a compõem |
| `POST` | `/api/v1/invoices/{id}/payments` | Paga total ou parcial |
| `DELETE` | `/api/v1/invoices/{id}/payments` | Desfaz o último pagamento |
| `POST` | `/api/v1/invoices/{id}/close` | Fecha a fatura |
| `GET` | `/api/v1/recurrences?month=2026-08&paused=0` | Regras fixas com o estado de cada uma no mês |
| `GET` | `/api/v1/recurrences/options` | Contas, cartões, categorias e frequências |
| `POST` `PUT` | `/api/v1/recurrences[/{id}]` | Cadastra e edita a regra |
| `PATCH` | `/api/v1/recurrences/{id}/status` | Pausa ou retoma |
| `DELETE` | `/api/v1/recurrences/{id}` | Exclui a regra (os lançamentos ficam) |
| `POST` | `/api/v1/recurrences/{id}/launch` | Lança a regra no mês informado |
| `POST` | `/api/v1/recurrences/launch` | Lança todas as pendentes do mês |
| `POST` | `/api/v1/imports/preview` | Lê o arquivo enviado e devolve as linhas — **não grava nada** |
| `POST` | `/api/v1/imports` | Grava as linhas confirmadas e abre o lote |
| `GET` | `/api/v1/imports` | Últimas importações, com o estado de cada uma |
| `DELETE` | `/api/v1/imports/{id}` | Desfaz o lote inteiro |

Registro e login têm rate limit de 6 tentativas por minuto.

### Filtros do extrato

`GET /api/v1/transactions` aceita `from`, `to` (padrão: mês corrente), `search`,
`account_id`, `credit_card_id`, `page`, `per_page` e as listas `origins[]`,
`types[]`, `statuses[]` e `categories[]`. Lista vazia significa "sem filtro".

A resposta traz `items` (a página), `summary` (totais do **período inteiro**, não
da página) e `meta` (paginação).

Um `401` da API dispara um evento que limpa o token e devolve a SPA para a tela de login.

---

## Testes

```bash
make test
```

Pest sobre PostgreSQL — a suíte não roda em SQLite porque o schema usa coluna gerada e as agregações mensais usam `TO_CHAR`. A base de teste (`orbe_test`) é criada no primeiro boot do container do Postgres.

Cobertura: **toda Action de cálculo tem teste** (parcelamento, pagamento e fechamento de fatura, transferência, projeção, divisão de centavos) e cada endpoint tem feature test de caminho feliz, autorização, validação e isolamento entre usuários.

---

## Escopo

Controle **manual**: os lançamentos são cadastrados na interface. Não há integração com Open Finance nem pagamento real de boleto ou fatura — "pagar fatura" registra a baixa no app, não move dinheiro de verdade.

A **importação de extrato OFX/CSV** entrou nesta versão: ela lê o arquivo que o
banco exporta e grava os lançamentos que você confirmar, mas continua sendo
controle manual — não há conexão com o banco, e o arquivo é você quem baixa e
envia.

## Estado da entrega

Entregue nesta versão:

- [x] Docker Compose completo (php-fpm, nginx, postgres, redis, worker, scheduler, Vite)
- [x] Domínio inteiro modelado: migrations, models, enums, actions e queries
- [x] Seeder de demonstração com sete meses de histórico em pt-BR
- [x] Autenticação (registro, login, logout, sessão)
- [x] Tela de **Visão geral** replicando a referência visual, com dados reais da API
- [x] Tela de **Lançamentos**: extrato unificado com filtros, CRUD completo e mudança de status
- [x] Tela de **Cartões**: limites, cadastro de cartão e faturas (detalhe, pagar, fechar, desfazer)
- [x] **Faturas inteligentes**: fechamento retroativo por convenção (agendado + preguiçoso) e antecipação manual
- [x] **Parcelamento em conta e empréstimo**: N lançamentos por mês, valor por total ou por prestação
- [x] Tela de **Despesas fixas**: regras de repetição e o ato de lançá-las no extrato
- [x] Tela de **Categorias**: árvore de dois níveis, CRUD e exclusão com realocação
- [x] Tela de **Importar histórico**: extrato OFX/CSV com conferência linha a
      linha, sugestão de categoria, aviso de repetido e desfazer do lote
- [x] CI (Pint, PHPStan 6, Pest, tsc, ESLint, build)

Próximas telas: Bancos e contas, Relatórios, Previsão financeira, Metas e orçamento.
