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

### Ponto (folha do RH)
- Botões **▶ Iniciar jornada / ⏹ Encerrar jornada** na barra do topo registram a entrada e a saída do dia — é o registro usado no fechamento da folha do RH.
- **Bloqueio total sem o ponto**: propor atividades e operar etapas (iniciar/pausar/retomar/concluir/editar) só com a jornada do dia iniciada; horários antes da entrada ou depois da saída registrada são recusados.
- **Encerramento automático**: ponto esquecido em aberto é fechado no **fim da jornada semanal prevista** do professor (marcado como `auto`/¹); sem jornada definida, usa o último apontamento real do dia.
- Correção na aba Jornada: card **"Registro de ponto"** mostra o ponto do mês escolhido e permite corrigir entrada/saída, registrar um dia esquecido ou **gerar o ponto pelos apontamentos** (completa de uma vez os dias com atividades e sem ponto, usando o primeiro início e o último término reais; não altera dias já registrados). Dias de afastamento médico não aceitam ponto.

### Apontamentos
- Ao propor uma atividade (título, data, início e duração estimada), o sistema preenche automaticamente a **previsão de início e término de cada etapa**, distribuindo a duração pelos pesos do modelo do professor.
- Botões **Iniciar/Pausar/Retomar/Concluir** registram os horários reais de cada etapa; edição manual disponível. Pausar congela a contagem para trabalhar em outra atividade — o tempo pausado não conta como trabalho e é descontado de todas as horas e relatórios.
- **Descansos**: almoço e/ou janta por dia — janela bloqueada: a previsão das etapas pula o intervalo, nenhum registro é aceito dentro dele, sem sobreposição, e as horas descontam o período.
- **Saúde (separada dos descansos)**: **saída médica** (retorno opcional — sem retorno, contam as horas restantes da jornada) e **afastamento médico** (1 dia ou mais, dias inteiros bloqueados), ambos com **anexo do atestado** (PDF/JPG/PNG até 5 MB, guardado em data/atestados/ com nome aleatório e download autenticado) — o atestado pode ser **anexado depois**, direto na etiqueta do registro no diário, já que só fica em mãos ao fim do atendimento (substituir apaga o arquivo anterior). Indicador próprio no painel: o desconto considera **apenas as horas de jornada**, nunca o tempo total fora do expediente.

### Diário
- Filtros por professor (gestão/admin), período (hoje/semana/mês/tudo ou datas livres) e busca.
- Indicadores: atividades, concluídas, em andamento, horas registradas, descanso e dias de trabalho.
- Exportação XLS com uma linha por etapa (inclui professor e RM).

### Jornada de trabalho e banco de horas
- Página própria para informar a **jornada semanal** (entrada e saída de cada dia, ex.: 07:00 às 14:40).
- O diário exibe a jornada do dia; um aviso aparece quando faltam 30 minutos para o fim do expediente e, passado o horário, o sistema oferece **registrar as horas excedentes no banco de horas** (calculadas dos apontamentos reais, líquidas de intervalos e pausas; um registro por dia, atualizável).
- Saldo e extrato do banco de horas na própria página da jornada.

### Ajuda integrada
- Página **Ajuda** (botão na barra superior) com tutorial ilustrado de cada funcionalidade — capturas reais do sistema com destaques e banners por seção, com conteúdo adaptado ao perfil de quem está logado (imagens em `assets/tutorial/`).

### Relatórios (com assinaturas)
- **Simplificado**: uma linha por dia com início e término do trabalho apontado, descansos e horas trabalhadas líquidas. Se o dia tem registros reais, os horários exibidos são somente os reais; dias sem registro real usam a previsão, marcados com `*`.
- **Detalhado**: todas as atividades e etapas do período, com previsão × real.
- **Folha de ponto** (fechamento do RH): entrada e saída registradas pelo ponto, intervalos do dia e horas do ponto (entrada → saída, descontados descansos e saídas médicas); saídas automáticas marcadas com ¹; dias de afastamento em linha própria com o status do atestado. Dias trabalhados **sem ponto registrado** (meses anteriores à adoção do ponto) aparecem com os horários deduzidos dos apontamentos, marcados com ² até o ponto ser gerado.
- Seletor de **Mês** que preenche o período com o mês inteiro (vale para os três tipos), além das datas livres.
- Todos saem com **campos de assinatura**: Maiberte Brogliato (Direção · CECAPE) e o professor (nome + RM), na prévia em tela, na impressão e no PDF.
- A prévia exibida se **atualiza automaticamente** a cada alteração (atestado anexado, ponto corrigido, etapa editada), de modo que impressão e PDF nunca saiam com dados antigos; havendo mais de um registro de saúde no mesmo dia, prevalece o que tem atestado anexado.
- Impressão em layout claro de documento; PDF gerado no navegador (jsPDF), com fallback para a impressão.

## Banco de dados: MySQL ou SQLite

A aplicação usa PDO e funciona com **dois bancos**, escolhidos pela configuração:

- **MySQL** (recomendado — gerenciável pelo phpMyAdmin): crie o banco e o usuário no cPanel (MySQL® Databases), copie `app/Config/config.local.php.example` para `config.local.php` e preencha `DB_MYSQL_HOST/NAME/USER/PASS`.
- **SQLite** (padrão, zero configuração): sem o `config.local.php`, os dados ficam em `data/diario-<aleatório>.sqlite`.

**Migração automática SQLite → MySQL**: na primeira conexão com o banco MySQL vazio, os dados de uma instalação SQLite existente em `data/` são importados automaticamente (contas, atividades, etapas, pausas e descansos, preservando os IDs); o arquivo `.sqlite` é renomeado para `.importado-<data>` e permanece como backup.

## Arquitetura (MVC)

PHP 8+ com PDO (MySQL ou SQLite), front controller único:

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
├── Models/                   User, Activity, Phase, Pausa, Jornada, BancoHoras, Saude, Ponto, LoginAttempt
└── Views/                    Layout, login e painel (abas por perfil)
```

Tabelas: `users` (perfil, RM, etapas em JSON, hash de senha), `activities` (por usuário), `phases`, `phase_pauses` (pausas de etapa), `breaks` (intervalos), `work_schedules` (jornada semanal), `hour_bank` (banco de horas), `medical_leaves` (saúde, com atestado), `time_clock` (ponto diário: entrada, saída e marcação de encerramento automático), `login_attempts`. No MySQL, `phases` e `phase_pauses` têm chave estrangeira com `ON DELETE CASCADE`. Migrações automáticas: banco SQLite antigo de usuário único ganha a coluna `user_id` e preserva a senha cadastrada; SQLite → MySQL importa tudo na primeira conexão.

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
