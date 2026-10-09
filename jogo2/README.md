# Jogo 2 — Expedição dos Sete Mares

Jogo educacional de Língua Portuguesa que usa as missões, perguntas e alternativas já cadastradas no banco `palavra_a_bordo`.

## Arquivos

- `index.php`: seleção de missões, perguntas e resultados.
- `api/`: endpoints JSON de missões, início, perguntas, respostas e resultado.
- `controllers/Jogo2Controller.php`: validação, tentativas, respostas e pontuação no servidor.
- `config/`: conexão PDO própria do Jogo 2 e bootstrap dos endpoints.
- `css/jogo2.css` e `js/jogo2.js`: interface e interação do jogo.

O Jogo 2 reutiliza a classe de sessão `Auth` e a arte local do navio do Jogo 1. O CSS e os endpoints permanecem isolados dentro de `jogo2/`. A conexão PDO do Jogo 2 é própria para que falhas de conexão possam retornar JSON sem modificar o conector compartilhado do Jogo 1.

## Banco de dados

O esquema existente já contém o jogo “Expedição dos Sete Mares”, sete categorias/desafios e 35 perguntas. Na conferência de 9 de outubro de 2026, cada missão tinha cinco perguntas, cada pergunta tinha quatro alternativas e havia exatamente uma alternativa correta. Por isso, não foi necessário criar ou executar um script de alteração de dados.

O conector utiliza por padrão o ambiente Laragon local:

- host: `localhost`
- porta: `3308`
- banco: `palavra_a_bordo`
- usuário local: `root`
- senha local: vazia

Em outro ambiente, configure as variáveis `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD`. Não use as credenciais locais em produção.

As tentativas usam as tabelas existentes `tentativas`, `respostas` e `usuarios`. A sessão autenticada do Jogo 1 é reutilizada; como no fluxo de convidado já adotado pelo Jogo 1, sessões sem usuário autenticado usam o jogador convidado de ID 1. Esse registro precisa existir. Não foi criada tabela, coluna nem moeda.

## Regras usadas

- Três vidas por tentativa; cada erro consome uma vida.
- Cada acerto vale 100 pontos.
- Concluir as cinco perguntas com pelo menos uma vida concede o bônus existente de 200 pontos.
- Ao terminar as vidas, a tentativa encerra como missão não concluída.
- Tentativas interrompidas permanecem salvas e podem ser retomadas pela seleção da missão.
- Os resultados são calculados no servidor. O bloqueio transacional da tentativa evita respostas duplicadas por cliques simultâneos.

## Executar e testar

1. Inicie o Apache e o MySQL do Laragon.
2. Confirme que o banco `palavra_a_bordo` contém as sete missões e o usuário convidado de ID 1 (ou entre com uma sessão autenticada pelo projeto).
3. Acesse `http://localhost/TCC/jogo2/`.
4. Inicie uma missão, escolha uma resposta, leia o feedback e use **Continuar**. Confira pontos, vidas, progresso e o resultado final.
5. Teste também a retomada de uma tentativa ao voltar às missões antes de concluí-la.

Os endpoints de leitura aceitam GET; início e envio de resposta aceitam POST com token CSRF da sessão. As respostas JSON não expõem detalhes internos de exceções.
