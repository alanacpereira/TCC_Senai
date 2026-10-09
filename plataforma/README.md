# Palavra a Bordo — plataforma

Esta pasta contém a plataforma PHP e a migração aditiva para integrar
os jogos existentes. Os arquivos de `jogo1/` e `jogo2/` não são recriados nem
alterados pela plataforma.

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

## Migração aplicada

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

## Configuração local

Requisitos usados/desenhados: PHP 8.3, PDO MySQL, MySQL 8.4 e Apache/Laragon.
Por padrão a conexão lê:

- `DB_HOST=localhost`
- `DB_PORT=3308`
- `DB_NAME=palavra_a_bordo`
- `DB_USER=root`
- `DB_PASSWORD=` (vazia somente no ambiente local Laragon)

Em ambientes compartilhados ou produção, defina as credenciais em variáveis
de ambiente, não em arquivos versionados. A plataforma e o Jogo 2 aceitam essas
variáveis; o conector legado do Jogo 1 permanece inalterado e usa sua própria
configuração existente.

Abra `http://localhost/TCC/plataforma/` e crie uma conta pelo cadastro de
estudante. A migração já está ativa neste banco local. A inscrição pública não
permite escolher perfil de professor.

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

## Provisionar professor

O papel de professor só pode ser criado no servidor. Com a migração instalada,
use o provisionador CLI, que cria uma conta nova e não altera uma conta
existente. Defina a senha forte no ambiente da sessão e não a inclua na linha
de comando:

```powershell
$env:PLATAFORMA_PROFESSOR_PASSWORD = "uma-senha-forte-de-uso-local"
php .\plataforma\bin\provisionar-professor.php "Nome do Professor" professor@example.org
Remove-Item Env:\PLATAFORMA_PROFESSOR_PASSWORD
```

O comando não pode ser executado por HTTP. Convites de professor têm estrutura
preparada no banco, mas o fluxo de envio e resgate ainda não está habilitado.

## Funcionalidades preparadas

- Cadastro exclusivo de estudantes, login com hash de senha, CSRF e limitação
  de tentativas de login.
- Sessão compartilhada com a classe `Auth` existente para abrir os jogos como
  o mesmo usuário; jogos, perguntas e endpoints não são reescritos.
- Painel de estudante com nível/XP existentes, tentativas e atalhos para os
  dois jogos.
- A carteira pontua tentativas concluídas usando a pontuação do servidor e
  chave idempotente por tentativa; não cria saldo até haver atividade
  concluída.
- As três conquistas atuais do Jogo 1 são avaliadas com base em respostas e
  tentativas armazenadas no servidor.
- O tesouro oferece quatro itens virtuais cosméticos, com custos definidos e
  resgate transacional registrado na coleção/histórico.
- Professores podem criar turmas; estudantes entram com código; relatórios são
  sempre filtrados pelos vínculos entre professor, turma e estudante.
- Perfil, alteração de senha e preferências visuais persistidas. Preferências
  afetam as páginas da plataforma; CSS isolado dos jogos não é alterado.
- Recuperação por e-mail com tokens de curta duração, uso único, limite de
  solicitações e suporte SMTP configurável.

Não foi inventada regra para evolução de nível; o painel apresenta nível e XP
já existentes. A carteira de pontos virtuais é separada da experiência.

## Verificações e limitações

Foram verificadas as contagens antes e depois da migração, as duplicidades de
respostas, o índice único e as 11 tabelas novas. Todos os 20 arquivos PHP
passaram em `php -l`. Testes funcionais locais cobriram cadastro, login,
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
- Acesso docente: crie cada conta de professor por meio do provisionador CLI,
  usando os dados e a senha definidos pela instituição.

Os dados de jogos, perguntas, alternativas, tentativas, respostas e conquistas
foram preservados. Não foram cadastrados itens, preços ou regras de recompensa
físicos nem vantagens pagas.
