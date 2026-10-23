# Palavra a Bordo — plataforma

Esta pasta contém a plataforma PHP e a migração aditiva para integrar
os jogos existentes. As perguntas e regras de jogo permanecem existentes;
as páginas e APIs receberam verificações de sessão para restringir o acesso
ao perfil de estudante.

## Diagnóstico realizado

Banco `palavra_a_bordo`, MySQL 8.4.3, Laragon local, porta 3308. O esquema e
os registros foram inspecionados antes da migração. Em 09/10/2026, após
autorização, a migração aditiva abaixo foi aplicada. Um backup completo foi
criado fora da pasta pública do site antes da alteração.

Na inspeção inicial, o esquema existente continha:

| Tabela | Registros observados |
| --- | ---: |
| `jogos` | 2 |
| `categorias` | 11 |
| `desafios` | 11 |
| `perguntas` | 48 |
| `alternativas` | 192 |
| `tentativas` | 6 |
| `respostas` | 10 |
| `usuarios` | 1 |
| `conquistas` | 3 |
| `usuario_conquistas` | 0 |

As relações de jogos, categorias, desafios, questões, alternativas, tentativas,
respostas e conquistas já estão protegidas por chaves estrangeiras. `usuarios`
já tem nome, e-mail único, hash de senha, nível, experiência e data de cadastro.
Não há perfil, preferências, saldo, catálogo de recompensas ou turmas.
Naquele momento, `usuarios` ainda não tinha perfil; as tabelas de preferências,
saldo, catálogo e turmas ainda não existiam. `tentativas` e `respostas` já
registravam progresso e pontuação; experiência já era somada pelo jogo quando
a missão/desafio era concluído. As três conquistas cadastradas são as do Jogo 1.

O usuário de ID 1 foi conferido como `Jogador convidado`. Nenhum usuário
estudante ou professor adicional existia no momento da inspeção. Não há dados
de envio de e-mail configurados.

Após a migração, as contagens dessas tabelas existentes foram conferidas e
permaneceram iguais. A conta convidada de ID 1 está marcada como `convidado`;
as 11 tabelas da plataforma e o índice único de respostas foram confirmados.

## Migrações aplicadas

Arquivo: `database/migrations/001_plataforma_palavra_a_bordo.sql`.

O script:

- Usa somente `palavra_a_bordo`.
- Não apaga, recria ou repovoa tabelas existentes.
- Adiciona `usuarios.perfil` apenas quando a coluna não existe; novas contas
  ficam como `aluno`, e a conta convidada legada é marcada como `convidado`
  somente quando o tipo da coluna aceita esse perfil e ID/nome correspondem.
- Cria, se ausentes, tabelas para preferências, carteira, extrato, recompensas,
  resgates, turmas/vínculos, convites, tokens de recuperação e limitação de
  login.
- Tenta adicionar uma restrição única para respostas por tentativa/pergunta
  somente se os dados atuais não tiverem duplicatas. Se houver duplicatas,
  preserva-as e informa que o índice foi ignorado.
- Exibe consultas de verificação ao final.

**A migração já foi executada neste banco local.** O script permanece no
repositório para auditoria e é idempotente. Não é necessário executá-lo de novo
para usar a plataforma. Em outra instalação, antes de executar:

1. Faça um backup do banco `palavra_a_bordo`.
2. Revise o arquivo SQL, inclusive as verificações e o tratamento do perfil
   convidado.
3. Execute apenas esse arquivo no banco correto pelo phpMyAdmin ou cliente
   MySQL; não execute `jogo1/dados_desafio_1.sql` nem qualquer carga de jogos.
4. Confira os resultados de verificação e compare as contagens das tabelas
   existentes antes e depois.

Consulta adicional de verificação somente leitura: `database/verificar.sql`.

## Dados dos desafios do Jogo 1

Depois de importar `localhost.sql` em uma instalação nova, carregue também
`jogo1/dados_desafio_1.sql` e `jogo1/dados_desafios_2_4.sql`. Esses arquivos
completam os quatro desafios com dez perguntas cada e podem ser executados
novamente sem duplicar perguntas ou alternativas. O segundo arquivo também
corrige a alternativa ortográfica marcada incorretamente no desafio de S e SS.

O catálogo digital foi inicializado por
`database/migrations/002_recompensas_digitais.sql`, também com `INSERT IGNORE`
para não substituir itens existentes. Ele define:

| Recompensa digital/cosmética | Custo |
| --- | ---: |
| Título: Grumete das Palavras | 100 pontos |
| Insígnia: Olho de Águia | 250 pontos |
| Baú: Mapa do Tesouro | 400 pontos |
| Título: Capitão das Palavras | 600 pontos |

Os custos formam uma progressão de colecionáveis virtuais: começam no valor de
um acerto correto de 100 pontos e aumentam conforme a raridade visual. Não são
dinheiro, não compram bens físicos e não alteram as regras ou dão vantagem nos
jogos. O tesouro registra a aquisição e o histórico do resgate.

A migração aditiva `database/migrations/003_recompensas_navegacao.sql` inclui
um peixinho companheiro, uma gema do mar e um chapéu de pirata, todos como
colecionáveis digitais sem entrega física. Para atualizar uma instalação que já
aplicou as migrações anteriores, execute esse arquivo uma vez no banco
`palavra_a_bordo`; o `INSERT IGNORE` preserva recompensas e resgates existentes.

O arquivo `database/migrations/003_perfil_avatar_acessibilidade.sql` adiciona
o identificador de avatar à conta e um indicador explícito de que as
preferências de acessibilidade foram configuradas. É uma migração aditiva,
idempotente e não altera os dados de progresso. Contas existentes mantêm um
avatar padrão; as preferências existentes só passam a ser consideradas
configuradas quando o usuário escolhe e salva uma opção. Ela foi aplicada no
banco local; as contagens de contas (4) e preferências (3) permaneceram iguais.

## Configuração local

Requisitos usados/desenhados: PHP 8.3, PDO MySQL, MySQL 8.4 e Apache/Laragon.
Por padrão a conexão lê:

- `DB_HOST=localhost`
- `DB_PORT=3308`
- `DB_NAME=palavra_a_bordo`
- `DB_USER=root`
- `DB_PASSWORD=` (vazia somente no ambiente local Laragon)

Em ambientes compartilhados ou produção, defina as credenciais em variáveis
de ambiente, não em arquivos versionados. A plataforma e os dois jogos usam
`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD`.

Abra `http://localhost/TCC/plataforma/` para criar uma conta de estudante ou
professor. Também é possível abrir `cadastro.php?perfil=professor` diretamente.
A migração já está ativa neste banco local.

## Recuperação de senha e e-mail

A recuperação está implementada com token aleatório de uso único, guardado no
banco somente como SHA-256, validade de uma hora, limite de três solicitações
por conta a cada 15 minutos e resposta que não revela se o e-mail existe.
Reutilizar um link invalida os links anteriores; após trocar a senha, todos os
tokens da conta são invalidados.

No ambiente de desenvolvimento Laragon foi usado o Mailpit já instalado,
restrito ao computador local:

- SMTP local: `localhost:1025`, sem autenticação ou TLS; não encaminha e-mails
  para fora.
- Caixa de entrada local: `http://127.0.0.1:8025/`.
- Inicie-o com:

```powershell
& "C:\Users\sesi2b\laragon\bin\mailpit\1.22.3\mailpit.exe" --smtp "[::1]:1025" --listen "127.0.0.1:8025"
```

O processo precisa continuar aberto para que a recuperação funcione localmente.
As mensagens de teste ficam na interface Mailpit e não são entregues a caixas
reais.

Para produção, forneça as variáveis abaixo ao processo do Apache/PHP e reinicie
o servidor; não as grave no repositório. Use os dados oficiais do provedor de
e-mail e um remetente autorizado:

```text
PLATAFORMA_BASE_URL=https://seu-dominio.example/plataforma
PLATAFORMA_SMTP_HOST=servidor-smtp-do-provedor
PLATAFORMA_SMTP_PORT=587
PLATAFORMA_SMTP_SECURITY=starttls
PLATAFORMA_SMTP_USER=usuario-smtp
PLATAFORMA_SMTP_PASSWORD=segredo-fornecido-pelo-provedor
PLATAFORMA_EMAIL_FROM=no-reply@seu-dominio.example
PLATAFORMA_EMAIL_FROM_NAME=Palavra a Bordo
```

Para SSL implícito, use `PLATAFORMA_SMTP_SECURITY=ssl` com a porta indicada
pelo provedor. Sem variáveis, a aplicação usa o Mailpit local e a URL
`http://localhost/TCC/plataforma`; não use esses padrões em produção.

## Criar conta de professor

O cadastro docente está disponível em `cadastro.php?perfil=professor` e pode ser
feito diretamente, sem convite. Após concluir o cadastro, a pessoa entra no
painel docente. Para acessar uma conta existente, use `login.php?perfil=professor`.

Para provisionamento administrativo direto, o script
`bin/provisionar-professor.php` permanece disponível para compatibilidade.

## Publicação

O workflow `.github/workflows/deploy.yml` valida a sintaxe de PHP e JavaScript
antes de publicar por FTP. Configure `FTP_SERVER`, `FTP_USERNAME` e
`FTP_PASSWORD` nos secrets do GitHub. Configure `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER` e `DB_PASSWORD` no ambiente PHP do servidor; a publicação
por FTP não configura variáveis do processo. Não use a conta local `root` sem
senha em produção.

O pacote de publicação exclui arquivos SQL, documentação, backups, arquivos de
ambiente e ferramentas locais de teste. O `.htaccess` também bloqueia esses
arquivos quando servido por Apache e desativa listagem de diretórios.

## Funcionalidades preparadas

- Página inicial pública com cenário náutico, apresentação dos acessos de
  jogador/professor, cadastro e login. Ela não lista jogos nem apresenta dados
  pessoais; os jogos e o progresso ficam na área autenticada do estudante.
- Cadastro público de estudantes com seleção segura de avatar e cadastro
  docente direto, sem convite. Os dois perfis são verificados no servidor.
  Login com hash de senha, CSRF e limitação de tentativas.
- Sessão compartilhada com a classe `Auth` existente. As páginas e APIs dos
  dois jogos exigem uma sessão de estudante válida; o fallback temporário para
  o usuário de ID 1 foi removido.
- Área protegida de estudante com nível/XP existentes, tentativas, conquistas,
  saldo e atalhos para os dois jogos. O perfil professor continua direcionado
  ao painel docente e não pode iniciar jogos de estudante.
- A carteira pontua tentativas concluídas usando a pontuação do servidor e
  chave idempotente por tentativa; não cria saldo até haver atividade
  concluída.
- As três conquistas atuais do Jogo 1 são avaliadas com base em respostas e
  tentativas armazenadas no servidor.
- O tesouro oferece quatro itens virtuais cosméticos, com custos definidos e
  resgate transacional registrado na coleção/histórico.
- Professores podem criar turmas; estudantes entram com código; o dashboard,
  busca de estudantes e relatório individual são limitados às turmas ativas
  associadas ao professor. Sinais de revisão usam aproveitamento abaixo de 60%
  nas respostas registradas e são apresentados como apoio, não diagnóstico.
- Perfil, alteração de senha, avatar selecionável e preferências de
  acessibilidade persistidas.
  A Central compartilhada funciona na plataforma e nos dois jogos sem alterar
  regras, perguntas, pontuação ou progresso.
- Recuperação por e-mail com tokens de curta duração, uso único, limite de
  solicitações e suporte SMTP configurável.

Não foi inventada regra para evolução de nível; o painel apresenta nível e XP
já existentes. A carteira de pontos virtuais é separada da experiência.

O visual da página inicial e dos componentes da plataforma está em
`css/plataforma.css`. O banner usa a imagem náutica existente
`../img/fundoinicial.png` (caminho relativo à folha CSS). Textos, navegação,
cards e botões continuam sendo elementos reais da página. A integração não
exige alteração adicional no MySQL. O card de prêmios reutiliza a ilustração
existente `../jogo1/css/img/ba%C3%BA.png` a partir da rota da plataforma.

## Verificações e limitações

Foram verificadas as contagens antes e depois das migrações, as duplicidades de
respostas, o índice único e as 11 tabelas novas. Na revisão final, os 42
arquivos PHP passaram em `php -l` e os 4 arquivos JavaScript passaram em
`node --check`. Testes funcionais locais cobriram cadastro, login,
preferências, navegação entre os jogos, criação de turma, entrada de estudante,
relatório docente, envio/captura de e-mail local, redefinição e uso único de
token, login com a senha redefinida e resgate de item com débito único. Os dados
temporários de testes são removidos ao final.

Ainda dependem de configuração/decisão do responsável:

- E-mail de produção: o Mailpit local permite validar o fluxo, mas não entrega
  mensagens reais. Para produção ainda é necessário configurar as credenciais
  e o domínio autorizado de um provedor SMTP institucional.
- O catálogo inicial contém apenas recompensas digitais e cosméticas; pontos
  não representam dinheiro, itens físicos ou vantagens nos jogos.

Os dados de jogos, perguntas, alternativas, tentativas, respostas e conquistas
foram preservados. Não foram cadastrados itens, preços ou regras de recompensa
físicos nem vantagens pagas.

Na revisão de acesso, as páginas dos dois jogos redirecionam visitantes para o
login; as APIs retornam 401 para chamadas anônimas e 403 para perfil não
autorizado. A revisão atual adicionou duas colunas por uma migração aditiva;
nenhuma conta ou preferência foi removida ou recriada.

O cadastro direto de professor foi validado com redirecionamento ao painel
docente e login posterior. O cadastro estudantil continuou funcional. As contas
temporárias usadas nos testes foram removidas; a base voltou a 4 usuários e 3
preferências.

## Central de Acessibilidade

O botão flutuante abre a Central nas páginas da plataforma, login, cadastro e
nos Jogos 1 e 2. Na primeira visita, uma pergunta não modal oferece ativar os
recursos ou seguir sem eles. Nenhum recurso opcional é ligado automaticamente.
As escolhas locais são gravadas no `localStorage`; a Central permanece
disponível para alterar a decisão depois. O painel é operável por teclado e
fecha com Esc.

Para contas autenticadas, o endpoint
`api/preferencias-acessibilidade.php` sincroniza as quatro opções com a tabela
existente `preferencias_acessibilidade`, usando a sessão e o token CSRF. A
migração 003 marca preferências explicitamente escolhidas; contas sem essa
escolha começam com os recursos desligados. Em jogos e plataforma, a escolha
local continua ativa se a sincronização da conta não estiver disponível. A
preferência de movimento do sistema operacional continua respeitada.
