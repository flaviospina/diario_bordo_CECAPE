# Diário de Bordo — CECAPE

Registro de atividades com **previsão automática das fases**: o administrador propõe a
atividade (título, início e duração) e o sistema calcula o **início e o término previstos de
cada fase**, respeitando a jornada de trabalho configurada. Depois, cada fase pode ter o
**início e o término reais** registrados, permitindo comparar previsto × realizado.

Os dados ficam em um banco **MySQL/MariaDB**, com **autenticação por usuário e senha** e dois
perfis de acesso. O back-end é PHP 8 puro (sem framework), pensado para rodar em hospedagem
compartilhada comum — HostGator, Locaweb, cPanel — sem nada além de PHP e MySQL.

---

## Perfis de acesso

| | **Administrador** | **Usuário (visualização)** |
|---|---|---|
| Ver painel e registros | ✅ | ✅ |
| Exportar XLS / PDF / imprimir | ✅ | ✅ |
| Criar, editar, duplicar e excluir atividades | ✅ | — |
| Registrar início/término reais das fases | ✅ | — |
| Replanejar as fases pendentes | ✅ | — |
| Cadastrar e gerenciar usuários | ✅ | — |
| Ver a trilha de auditoria | ✅ | — |
| Configurar jornada, identidade e importar backup | ✅ | — |

**Não existe auto-cadastro**: as contas são criadas por um administrador, que define o perfil
e uma senha inicial de troca obrigatória no primeiro acesso.

---

## Instalação

### 1. Banco de dados

```bash
mysql -u root -p < sql/schema.sql
```

Crie um usuário com **privilégio mínimo** (as linhas estão comentadas no fim do
`sql/schema.sql`) — sem `CREATE`, `DROP`, `ALTER` nem `GRANT`:

```sql
CREATE USER 'diario_app'@'localhost' IDENTIFIED BY 'uma-senha-forte-aqui';
GRANT SELECT, INSERT, UPDATE, DELETE ON diario_bordo.* TO 'diario_app'@'localhost';
FLUSH PRIVILEGES;
```

Em painéis tipo cPanel, crie o banco e o usuário pela interface e importe o `schema.sql` pelo
phpMyAdmin — o arquivo já traz `SET NAMES utf8mb4` no topo para os acentos não corromperem.

### 2. Configuração

```bash
cp api/config.example.php api/config.php
```

Edite `api/config.php` com o host, o nome do banco, o usuário e a senha. Mantenha
`exigir_https => true` em produção e `depuracao => false`.

Este arquivo **não vai para o Git** (está no `.gitignore`) e o `.htaccess` bloqueia o acesso
a ele pela web. Se a hospedagem permitir, prefira guardá-lo fora do `public_html` e ajustar o
caminho em `api/index.php`.

### 3. Primeiro administrador

```bash
php ferramentas/criar-admin.php
```

A ferramenta pergunta nome, login, e-mail e senha (digitada sem aparecer na tela) e **só roda
pela linha de comando** — não fica um instalador exposto no servidor. Se o seu plano não dá
acesso a SSH, rode o comando localmente apontando para o banco remoto, ou insira o usuário
manualmente usando um hash gerado por `password_hash()`.

### 4. Publicação

Envie tudo para a pasta pública, exceto `tests/`, `node_modules/` e o `.git/`. A estrutura
final no servidor fica assim:

```
public_html/
├── index.html, login.html, .htaccess
├── assets/
├── api/           (index.php, lib/, rotas/, config.php, .htaccess)
├── sql/           (opcional — o .htaccess bloqueia o acesso)
└── ferramentas/   (opcional — só roda por CLI)
```

Ative o HTTPS e descomente o bloco de redirecionamento e o `Strict-Transport-Security` no
`.htaccess` da raiz. **Sem HTTPS o cookie de sessão trafega em claro** — em produção, isso é
o mínimo indispensável.

---

## Segurança

O que está implementado, e por quê:

**Contra SQL injection**
- Todas as consultas usam *prepared statements* com parâmetros vinculados, e
  `PDO::ATTR_EMULATE_PREPARES` está **desligado** — instrução e dados viajam separados até o
  servidor, que nunca interpreta o valor como SQL.
- Nenhum valor vindo do cliente é concatenado em SQL. O que precisa ser dinâmico (a ordenação
  da listagem, o `LIMIT` da auditoria) passa por **lista branca** ou vai como parâmetro
  inteiro vinculado.
- Os curingas `%` e `_` da busca são escapados antes de entrar no `LIKE`, com `ESCAPE`
  explícito — um `%` digitado é um caractere, não um operador.
- O usuário do banco não tem DDL: mesmo que uma injeção passasse, não haveria como criar,
  alterar ou remover tabelas.
- A suíte `tests/api.test.js` dispara cargas reais (`' OR '1'='1`, `'; DROP TABLE usuarios;--`,
  `UNION SELECT` sobre a tabela de senhas, `SLEEP()` para injeção cega) contra o login, a
  busca, a ordenação e os identificadores, e confere que nada autentica, nada atrasa e as
  tabelas continuam de pé.

**Senhas e autenticação**
- Hash com **Argon2id** (bcrypt custo 12 como alternativa quando o PHP não tem Argon2), com
  *rehash* automático se o algoritmo mudar.
- Política mínima: 10 caracteres, com letras e números, sem conter o login, o e-mail ou o
  nome, e sem as senhas óbvias.
- Login por usuário **ou** e-mail, com resposta sempre idêntica (`Usuário ou senha inválidos.`)
  e verificação de hash mesmo para contas inexistentes — nem a mensagem nem o tempo de
  resposta revelam quais contas existem.
- Bloqueio progressivo por conta (5 falhas) e por IP (25 falhas, limite mais folgado para não
  travar uma escola inteira que sai por um mesmo IP). O tempo é calculado no banco, sem
  misturar relógios.

**Sessão**
- Cookie `HttpOnly` (invisível ao JavaScript), `SameSite=Strict`, `Secure` sob HTTPS.
- `session_regenerate_id()` no login, na troca de senha e a cada 15 minutos.
- Expira por inatividade (30 min) e por tempo absoluto (8 h), e é derrubada se o navegador de
  origem mudar.
- Desativar ou excluir uma conta encerra a sessão dela na requisição seguinte.

**CSRF e autorização**
- Token CSRF por sessão, exigido no cabeçalho `X-CSRF-Token` de toda requisição que altera
  dados — inclusive o login. Comparação com `hash_equals`.
- O perfil é verificado **no servidor, em cada rota**. Esconder um botão é conveniência; a
  barreira real está na API, e o teste de interface confirma isso chamando a API diretamente
  como usuário de visualização (403).

**Entrada e saída**
- Toda entrada passa por validação de tipo, tamanho, faixa e formato, com lista branca de
  campos — o que não é esperado é ignorado. Backups importados passam pelas mesmas regras.
- Erros de banco nunca chegam ao cliente (podem conter trechos de SQL); ficam no log do
  servidor.
- Cabeçalhos de segurança: `Content-Security-Policy`, `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, e
  `no-store` nas páginas que mostram dados de sessão.
- Escritas com várias tabelas rodam em transação: ou grava tudo, ou não grava nada.

**Auditoria**
- Tabela `auditoria` registra login, login negado, acesso negado, criação/edição/exclusão de
  atividades e usuários, troca de senha e importação, com usuário, IP e horário. Senhas e
  hashes nunca são gravados ali.

### O que ainda depende de você

- **HTTPS com certificado válido** — sem isso, senha e cookie trafegam em claro.
- **Backup do banco** — o sistema exporta JSON, mas o backup do MySQL é responsabilidade da
  hospedagem/administrador.
- **Senha forte no usuário do banco** e no primeiro administrador.
- **Manter o PHP atualizado** na hospedagem.
- Não há 2FA nem recuperação de senha por e-mail: a redefinição é feita por um administrador.

---

## Como usar

### Registrar uma atividade (admin)

1. Entre com seu login → aba **Nova atividade**.
2. Preencha título, responsável, categoria, local e o **início previsto**.
3. Defina o término por **duração total** (ex.: 4h) ou informando o **término previsto**.
4. Escolha um **modelo de fases** (Padrão, Aula/Formação, Projeto, Reunião, Visita técnica ou
   Personalizado) e ajuste os pesos. As fases podem ser renomeadas, reordenadas, adicionadas
   e removidas.
5. A tabela **Previsão calculada** atualiza em tempo real. Salve.

### Acompanhar a execução (admin)

No detalhe da atividade, use **Iniciar** / **Concluir** em cada fase para carimbar o horário
atual, ou digite as datas nos campos de início/término reais. Progresso, situação e durações
totais se atualizam sozinhos.

**Replanejar restante** recalcula a previsão das fases ainda não concluídas a partir de agora
— útil quando a atividade atrasou.

### Consultar e exportar (qualquer perfil)

Na aba **Registros**, filtre por texto, período, responsável, categoria e situação. Os três
botões de exportação respeitam os filtros aplicados:

- **Exportar XLS** — planilha Excel com três abas (`Atividades`, `Fases`, `Informações`), com
  datas tipadas como data/hora, prontas para filtro e cálculo.
- **Exportar PDF** — relatório A4 paisagem com cabeçalho, uma seção por atividade, tabela de
  fases e numeração de páginas. Gerado nativamente, sem biblioteca externa.
- **Imprimir** — abre uma versão limpa e chama a impressão do navegador (que também permite
  "Salvar como PDF").

### Gerenciar usuários (admin)

Aba **Usuários**: criar contas, definir perfil, ativar/desativar, redefinir senha e excluir.
O sistema impede rebaixar ou desativar a própria conta e nunca deixa ficar sem administrador
ativo. Abaixo, a **trilha de auditoria** mostra as últimas ações.

---

## Jornada de trabalho

Todo o cálculo de previsão usa a jornada definida em **Configurações**:

- **Horário comercial** — dias úteis, expediente e intervalo de almoço opcional
  (padrão: seg–sex, 08:00–18:00, pausa 12:00–13:00);
- **Contínuo (24h)** — sem interrupções, para plantões e atividades ininterruptas.

As durações são sempre **tempo útil**. Uma atividade de 6h que começa às 10:00 termina às
17:00 no mesmo dia, porque o almoço não é contabilizado; o que não couber no expediente
transborda para o próximo dia útil.

## Situações

| Situação | Quando aparece |
|---|---|
| **Planejada** | ainda não iniciada e dentro do prazo |
| **Em andamento** | pelo menos uma fase iniciada, prazo não vencido |
| **Atrasada** | término previsto já passou e a atividade não foi concluída |
| **Concluída** | todas as fases com término real registrado |
| **Cancelada** | marcada manualmente pelo administrador |

---

## Estrutura do projeto

```
index.html                  aplicação (painel, registros, formulário, usuários, configurações)
login.html                  autenticação e troca obrigatória de senha
.htaccess                   cabeçalhos de segurança e bloqueio de arquivos privados

assets/css/style.css        design system: tokens, tema claro/escuro, componentes, impressão
assets/js/util.js           datas, formatação pt-BR, helpers de DOM
assets/js/schedule.js       motor de previsão: jornada, minutos úteis e distribuição das fases
assets/js/api.js            cliente HTTP (CSRF, sessão, normalização de erros)
assets/js/store.js          cache em memória, regras de situação/progresso e filtros
assets/js/pdf.js            gerador de PDF (Helvetica/WinAnsi, múltiplas páginas)
assets/js/export.js         exportação XLS (SpreadsheetML), relatório PDF e versão de impressão
assets/js/app.js            interface: navegação, sessão, formulário e ações
assets/js/login.js          tela de acesso

api/index.php               ponto de entrada único e roteador
api/lib/Bd.php              PDO (prepared statements, sem emulação)
api/lib/Seguranca.php       cabeçalhos, sessão, CSRF, força bruta
api/lib/Auth.php            login, perfis e hash de senha
api/lib/Validador.php       validação e normalização de entrada
api/lib/Auditoria.php       trilha de auditoria
api/lib/Resposta.php        respostas JSON e erros
api/rotas/                  auth, atividades, usuários, configurações
api/.htaccess               bloqueio de acesso direto a lib/, rotas/ e config.php

sql/schema.sql              esquema completo + configuração inicial
ferramentas/criar-admin.php criação do primeiro administrador (somente CLI)
tests/                      testes automatizados
```

### A API

Rotas em `api/index.php?r=recurso/acao` (funciona sem `mod_rewrite`; com ele, `api/atividades`
também). `GET` para leitura, `POST` para escrita, sempre JSON.

| Rota | Método | Quem pode |
|---|---|---|
| `auth/sessao` | GET | qualquer um (devolve o token CSRF) |
| `auth/login`, `auth/logout`, `auth/senha` | POST | conforme o caso |
| `atividades`, `atividades/obter` | GET | autenticado |
| `atividades/criar`, `atividades/atualizar`, `atividades/excluir` | POST | admin |
| `usuarios`, `auditoria` | GET | admin |
| `usuarios/criar`, `usuarios/atualizar`, `usuarios/senha`, `usuarios/excluir` | POST | admin |
| `config` | GET | autenticado |
| `config/salvar`, `config/importar` | POST | admin |

### O motor de previsão (`schedule.js`)

- `janelasDoDia(data, jornada)` — janelas de trabalho do dia, em minutos.
- `proximoInstanteUtil(data, jornada)` — avança para o próximo momento válido da jornada.
- `somarMinutosUteis(data, minutos, jornada)` — soma tempo útil pulando noites, fins de semana
  e intervalos.
- `minutosUteisEntre(a, b, jornada)` — tempo útil entre dois instantes.
- `planejar({ inicio, duracaoMin, fases, distribuir, jornada })` — rateia a duração entre as
  fases (por peso ou por duração fixa) e devolve início/término previstos de cada uma,
  encadeadas sem lacunas. O arredondamento sobra na última fase, então a soma das fases é
  sempre exatamente a duração informada.

---

## Testes

```bash
npm test                 # 36 testes do motor de previsão e formatação (só Node)
```

Com o servidor no ar (`npm start` sobe o PHP embutido em http://localhost:8124):

```bash
npm run test:api         # 88 verificações: autenticação, CSRF, perfis, validação,
                         # força bruta e cargas reais de SQL injection
npm i -D playwright
npm run test:interface   # 39 verificações ponta a ponta no Chromium + capturas de tela
npm run test:pdf         # 26 verificações estruturais do PDF gerado
```

As variáveis `ADMIN_USER` e `ADMIN_PASS` apontam a conta usada pelos testes. Os testes criam e
removem os próprios dados — ainda assim, **rode-os contra um banco de desenvolvimento**, nunca
em produção.

## Compatibilidade

- **Servidor**: PHP 8.0+ com PDO/MySQL, e MySQL 5.7+ ou MariaDB 10.4+.
- **Navegadores**: Chrome, Edge, Firefox e Safari atuais. Usa `color-mix()` e `:has()` em
  detalhes visuais — em navegadores antigos a interface continua funcional, com menos
  refinamento.
