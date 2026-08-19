# Diário de Bordo · CECAPE

Diário de bordo para registro das atividades executadas em home office, com dia e horário de início e término de cada atividade e de cada fase. Serve como controle dos projetos executados durante o período de trabalho remoto, permitindo que a direção acompanhe o andamento em modo de consulta.

**Produção:** `https://cecapescs.com.br/diariobordo`

## Como funciona

O sistema tem **dois modos de acesso**:

| Modo | Endereço | Quem usa | O que pode fazer |
|---|---|---|---|
| **Administrador** | `index.php?r=admin` | Somente o responsável pelo diário (protegido por senha) | Propor atividades, iniciar/concluir fases, editar e excluir registros, trocar a senha |
| **Consulta** | `index.php` (raiz) | Diretor e demais interessados (sem senha) | Visualizar, filtrar, exportar XLS e PDF e imprimir |

### Previsão automática das fases

Ao propor uma atividade, o administrador informa **título, data, horário de início e duração estimada**. O sistema preenche automaticamente a **previsão de início e término de cada fase**, distribuindo a duração pelos pesos configurados:

1. Planejamento — 15%
2. Execução — 55%
3. Verificação e ajustes — 20%
4. Conclusão e registro — 10%

As fases e os pesos podem ser ajustados livremente em cada atividade antes de registrar. Durante o trabalho, os botões **Iniciar** e **Concluir** registram os horários reais de cada fase (também é possível editá-los manualmente).

### Identidade visual

Interface no padrão visual CECAPE / AutoriaSCS: tema escuro (navy `#0b1628`) com acentos em ciano e laranja, tipografia Inter, cartões de indicadores coloridos, badges de status e rodapé institucional. A barra superior e a tela de login exibem as logos SEEDUC, AutoriaSCS e CECAPE, servidas de `https://cecapescs.com.br/logos` (o caminho pode ser alterado por `LOGO_BASE` no `config.local.php`). A impressão e o PDF são convertidos automaticamente para tema claro, próprio para papel.

### Consulta e exportação

- Filtros por período (hoje, semana, mês, tudo ou datas livres) e busca por texto.
- Resumo com total de atividades, concluídas, em andamento, horas registradas e dias de trabalho.
- Exportação para **XLS**, **PDF** e **impressão** com layout próprio.

## Arquitetura (MVC)

Aplicação PHP 8+ com PDO/SQLite em estrutura MVC com front controller único:

```
index.php                     Front controller — único ponto de entrada (rotas via ?r=...)
.htaccess                     Bloqueia app/ e data/, URLs amigáveis (Apache)
nginx.conf.example            Blocos equivalentes para VPS com Nginx
assets/                       CSS e JavaScript (públicos)
data/                         Banco SQLite com nome aleatório (criado automaticamente)
app/
├── bootstrap.php             Autoloader PSR-4 e helpers (escape de saída, URLs)
├── Config/config.php         Configurações (fuso, limites, fases padrão, logos)
├── Config/config.local.php.example  Ajustes por servidor (banco fora da raiz web, LOGO_BASE)
├── Core/
│   ├── Router.php            Mapeamento método+rota → controller/ação
│   ├── Controller.php        Base: respostas JSON, corpo da requisição, guardas
│   ├── View.php              Renderização de templates com layout
│   ├── Database.php          PDO singleton + migrações (schema criado no 1º acesso)
│   ├── Session.php           Sessão endurecida (cookie restrito, expiração, regeneração)
│   └── Csrf.php              Emissão e validação de token CSRF
├── Controllers/
│   ├── DiarioController.php  Página de consulta
│   ├── AdminController.php   Página de administração
│   └── ApiController.php     API JSON (leitura pública; escrita autenticada)
├── Models/
│   ├── Activity.php          Atividades + cálculo da previsão das fases
│   ├── Phase.php             Horários reais das fases
│   ├── Setting.php           Configurações persistidas (hash da senha)
│   └── LoginAttempt.php      Controle de tentativas de login
└── Views/
    ├── layouts/main.php      Layout base (cabeçalho, meta CSRF, assets)
    ├── diario/index.php      Modo consulta
    ├── admin/index.php       Modo administrador (login, formulário, troca de senha)
    └── partials/board.php    Painel compartilhado (resumo, filtros, lista)
```

## Segurança

- **PDO com prepared statements** em todas as consultas (`ATTR_EMULATE_PREPARES` desativado).
- **CSRF**: toda requisição de escrita exige o token da sessão no cabeçalho `X-CSRF-Token`.
- **Força bruta**: 5 senhas erradas em 15 minutos bloqueiam novas tentativas do IP.
- **Sessão**: cookie `HttpOnly` + `SameSite=Lax` + `Secure` (em HTTPS), restrito ao diretório da aplicação; `session_regenerate_id` no login (contra fixação); expiração por inatividade (8h).
- **Senha**: hash `password_hash`/bcrypt armazenado no banco; troca autenticada pelo próprio painel (exige a senha atual, mínimo de 8 caracteres); re-hash automático quando o algoritmo padrão do PHP evoluir.
- **Escape de saída** em todas as views (`e()`) e no front-end (`esc()`); validação e limites de tamanho em todas as entradas.
- **Cabeçalhos**: Content-Security-Policy, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy.
- **Código e dados inacessíveis**, com defesa em camadas que funciona em Apache e Nginx:
  - `.htaccess` nega `app/` e `data/` (Apache com AllowOverride);
  - `nginx.conf.example` traz os blocos equivalentes para Nginx;
  - todo arquivo PHP da aplicação sai vazio se chamado diretamente (guarda `APP_RUNNING`);
  - o arquivo do banco recebe **nome aleatório** não adivinhável por URL (registrado em `data/dbname.php`, que também sai vazio via web), e `index.html` vazios impedem listagem de diretório;
  - opcionalmente, `config.local.php` move o banco para **fora da raiz web** (recomendado em VPS).
- **Erros** nunca exibidos ao visitante (registrados no log do servidor).

## Instalação em VPS (cecapescs.com.br/diariobordo)

1. Crie a pasta `diariobordo` dentro do **document root do domínio** `cecapescs.com.br` e envie todos os arquivos do projeto para ela (via git clone, SFTP ou painel — preserve os `.htaccess`).
2. Garanta PHP 8+ com SQLite: `php -v` e `php -m | grep -i sqlite` (em Debian/Ubuntu: `sudo apt install php-sqlite3`).
3. Dê permissão de escrita na pasta `data/` ao usuário do PHP (ex.: `chown -R www-data:www-data diariobordo` ou `chmod 775 diariobordo/data`).
4. **Se o servidor for Nginx** (ou Apache com AllowOverride desativado), aplique os blocos de `nginx.conf.example` na configuração do domínio e recarregue o serviço.
5. (Recomendado) Copie `app/Config/config.local.php.example` para `config.local.php` e aponte o banco para fora da raiz web.
6. Acesse `https://cecapescs.com.br/diariobordo/index.php?r=admin`, entre com a senha inicial **cecape2026** e **troque-a imediatamente** em "Trocar senha do administrador".
7. Verifique a proteção: `https://cecapescs.com.br/diariobordo/data/` e `.../diariobordo/app/Config/config.php` devem retornar erro ou página vazia.
8. Compartilhe com a direção apenas `https://cecapescs.com.br/diariobordo/` (modo consulta).

O banco SQLite é criado automaticamente no primeiro acesso — não é preciso configurar MySQL nem editar arquivos.

Para testar localmente:

```bash
php -S localhost:8000
# consulta: http://localhost:8000/index.php
# admin:    http://localhost:8000/index.php?r=admin
```
