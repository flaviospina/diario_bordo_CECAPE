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

### Consulta e exportação

- Filtros por período (hoje, semana, mês, tudo ou datas livres) e busca por texto.
- Resumo com total de atividades, concluídas, em andamento, horas registradas e dias de trabalho.
- Exportação para **XLS**, **PDF** e **impressão** com layout próprio.

## Arquitetura (MVC)

Aplicação PHP 8+ com PDO/SQLite em estrutura MVC com front controller único:

```
index.php                     Front controller — único ponto de entrada (rotas via ?r=...)
.htaccess                     Bloqueia app/ e data/, URLs amigáveis, sem listagem de diretórios
assets/                       CSS e JavaScript (públicos)
data/                         Banco SQLite (negado ao navegador, criado automaticamente)
app/
├── bootstrap.php             Autoloader PSR-4 e helpers (escape de saída, URLs)
├── Config/config.php         Configurações (fuso, limites, fases padrão)
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
- **Código e dados inacessíveis**: `app/` e `data/` negados por `.htaccess`; além disso todo arquivo PHP da aplicação sai vazio se chamado diretamente (guarda `APP_RUNNING`).
- **Erros** nunca exibidos ao visitante (registrados no log do servidor).

## Instalação (HostGator ou similar)

1. Envie todos os arquivos para `public_html/diariobordo` (mantendo os `.htaccess`).
2. Acesse `https://cecapescs.com.br/diariobordo/index.php?r=admin` e entre com a senha inicial: **cecape2026**.
3. **Troque a senha imediatamente** no painel, em "Trocar senha do administrador".
4. Compartilhe com a direção apenas `https://cecapescs.com.br/diariobordo/` (modo consulta).

O banco SQLite é criado automaticamente no primeiro acesso — não é preciso configurar MySQL nem editar arquivos.

Para testar localmente:

```bash
php -S localhost:8000
# consulta: http://localhost:8000/index.php
# admin:    http://localhost:8000/index.php?r=admin
```
