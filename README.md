# Diário de Bordo · CECAPE

Diário de bordo multiusuário para registro das atividades executadas em home office, com dia e horário de início e término de cada atividade, de cada etapa e dos descansos (almoço/janta). Serve como controle dos projetos executados no trabalho remoto, com relatórios assinados pela direção e pelo professor.

**Produção:** `https://cecapescs.com.br/diariobordo`

## Perfis de acesso (tela de login única)

| Perfil | Quem | O que faz |
|---|---|---|
| **Administrador** | Prof. Flávio Spina (`flavio`) | Cria e gerencia as contas, registra os próprios apontamentos (atua também como professor) e consulta tudo |
| **Gestão** | Maiberte Brogliato (`maiberte`) e Therezinha (`therezinha`) | Consulta o diário de qualquer professor e gera os relatórios (simplificado ou detalhado) |
| **Professor** | Contas criadas pelo administrador | Registra as próprias atividades, etapas e descansos; gera o próprio relatório com campos de assinatura |

Senha inicial de todas as contas semeadas: **cecape2026** — cada pessoa troca a própria senha no painel ("Trocar minha senha"). Somente o administrador cria contas novas (perfil professor), definindo **nome, RM (registro de matrícula), usuário e as etapas de trabalho** conforme a função — ex.: "Planejamento / Execução / Verificação e ajustes / Conclusão e registro" — em qualquer quantidade, nome e peso.

## Funcionalidades

### Apontamentos
- Ao propor uma atividade (título, data, início e duração estimada), o sistema preenche automaticamente a **previsão de início e término de cada etapa**, distribuindo a duração pelos pesos do modelo do professor.
- Botões **Iniciar/Pausar/Retomar/Concluir** registram os horários reais de cada etapa; edição manual disponível. Pausar congela a contagem para trabalhar em outra atividade — o tempo pausado não conta como trabalho e é descontado de todas as horas e relatórios.
- **Descansos**: registro do horário de almoço e/ou janta por dia — janela realmente bloqueada: a previsão das etapas pula o intervalo, nenhum registro (automático ou manual) é aceito dentro dele, descansos não podem se sobrepor e todas as horas (diário, indicadores e relatórios) descontam a sobreposição com o descanso.

### Diário
- Filtros por professor (gestão/admin), período (hoje/semana/mês/tudo ou datas livres) e busca.
- Indicadores: atividades, concluídas, em andamento, horas registradas, descanso e dias de trabalho.
- Exportação XLS com uma linha por etapa (inclui professor e RM).

### Relatórios (com assinaturas)
- **Simplificado**: uma linha por dia com início e término do trabalho apontado, descansos e horas trabalhadas líquidas. Se o dia tem registros reais, os horários exibidos são somente os reais; dias sem registro real usam a previsão, marcados com `*`.
- **Detalhado**: todas as atividades e etapas do período, com previsão × real.
- Ambos saem com **campos de assinatura**: Maiberte Brogliato (Direção · CECAPE) e o professor (nome + RM), na prévia em tela, na impressão e no PDF.
- Impressão em layout claro de documento; PDF gerado no navegador (jsPDF), com fallback para a impressão.

## Arquitetura (MVC)

PHP 8+ com PDO/SQLite, front controller único:

```
index.php                     Front controller (rotas via ?r=...)
.htaccess                     Bloqueia app/ e data/ (Apache); nginx.conf.example p/ Nginx
assets/                       CSS e JavaScript
data/                         Banco SQLite com nome aleatório (criado no 1º acesso)
app/
├── bootstrap.php             Autoloader e helpers
├── Config/config.php         Configurações (perfis, contas iniciais, direção, logos)
├── Core/                     Router, Controller, View, Database, Session, Csrf
├── Controllers/
│   ├── PanelController.php   Login (form nativo + redirect) e painel por perfil
│   └── ApiController.php     API JSON (sessão, apontamentos, descansos, contas)
├── Models/                   User, Activity, Phase, Pausa, Setting, LoginAttempt
└── Views/                    Layout, login e painel (abas por perfil)
```

Banco: `users` (perfil, RM, etapas em JSON, hash de senha), `activities` (por usuário), `phases`, `phase_pauses` (pausas de etapa), `breaks`, `login_attempts`. Migração automática: banco antigo de usuário único ganha a coluna `user_id`, as atividades existentes passam para o admin e a senha já cadastrada é preservada.

## Segurança

- Login único obrigatório (nenhum dado é público); autorização por perfil em toda a API; cada professor só escreve nos próprios registros.
- Login/logout por formulário nativo com redirect do servidor (funciona sem JavaScript) + CSRF em toda escrita (cabeçalho `X-CSRF-Token` ou campo `_csrf`).
- Bloqueio de força bruta (5 falhas/15 min por IP); senhas bcrypt com re-hash automático; sessão `HttpOnly`/`SameSite`/`Secure`, regenerada no login, expirada por inatividade.
- PDO com prepared statements; validação e limites em todas as entradas; erros só no log; cabeçalhos CSP, X-Frame-Options etc.; `app/` e `data/` inacessíveis via web (`.htaccess` + guarda `APP_RUNNING` + nome de banco aleatório).
- Front-end resiliente: fontes e bibliotecas de exportação carregam sem bloquear o painel (CDN fora do ar não trava o sistema).

## Instalação (HostGator ou similar)

1. Envie todos os arquivos para a pasta do domínio (ex.: `.../diariobordo`), preservando os `.htaccess`.
2. Garanta PHP 8+ no MultiPHP Manager.
3. Acesse `https://cecapescs.com.br/diariobordo/` — o banco e as três contas iniciais são criados sozinhos.
4. Entre como `flavio` / **cecape2026**, troque sua senha e crie as contas de professor.
5. Informe Maiberte (`maiberte`) e Therezinha (`therezinha`) — senha inicial **cecape2026**, a trocar no primeiro acesso.

Para testar localmente: `php -S localhost:8000` e abra `http://localhost:8000/index.php`.
