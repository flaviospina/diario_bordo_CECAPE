<?php
defined('APP_RUNNING') or exit;
/** @var array $user Usuário logado */
$role = $user['role'];
$capable = $role !== 'gestor';
$admin = $role === 'admin';
$T = 'assets/tutorial';
?>
  <main class="main-content">
    <div class="page-container tut">
      <div class="page-header">
        <h1 class="page-title">Como usar o Diário de Bordo</h1>
        <p class="page-sub">Tutorial completo, funcionalidade por funcionalidade. Use o índice para ir direto ao que precisa.</p>
      </div>

      <nav class="panel-card tut-index">
        <div class="panel-title">Índice</div>
        <div class="tut-index-links">
          <a href="#acesso">🔐 Acesso e senha</a>
          <?php if ($capable): ?><a href="#ponto">⏱ Ponto do dia</a><?php endif; ?>
          <?php if ($capable): ?><a href="#registrar">✏️ Registrar atividades</a><?php endif; ?>
          <?php if ($capable): ?><a href="#intervalos">☕ Descansos e saídas</a><?php endif; ?>
          <a href="#diario">📒 Diário</a>
          <?php if ($capable): ?><a href="#jornada">🕐 Jornada e banco de horas</a><?php endif; ?>
          <a href="#relatorios">🖨 Relatórios</a>
          <?php if ($admin): ?><a href="#contas">👥 Contas da equipe</a><?php endif; ?>
          <a href="#dicas">💡 Dicas rápidas</a>
        </div>
      </nav>

      <!-- ═══════════ ACESSO ═══════════ -->
      <section class="tut-section" id="acesso">
        <img class="tut-hero" src="<?= $T ?>/hero-acesso.jpg" alt="Acesso ao sistema">
        <p>O sistema tem uma <b>tela de login única</b>: administrador, gestão e professores entram pelo mesmo endereço, cada um com seu usuário e senha. Nada fica visível sem login.</p>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-login.jpg" alt="Tela de login">
          <figcaption>Tela de login — informe seu usuário (ex.: <code>nome.sobrenome</code>) e a senha.</figcaption>
        </figure>
        <div class="tut-cols">
          <div>
            <h3>Perfis de acesso</h3>
            <ul>
              <li><b>Administrador</b> — gerencia as contas, registra os próprios apontamentos e consulta tudo.</li>
              <li><b>Gestão</b> (direção) — consulta o diário de qualquer professor e gera relatórios. Não edita nada.</li>
              <li><b>Professor</b> — registra as próprias atividades, descansos e jornada; gera o próprio relatório.</li>
            </ul>
          </div>
          <div>
            <h3>Trocar a senha</h3>
            <p>Na aba <b>Diário</b>, ao final da página, abra <b>"🔒 Trocar minha senha"</b>. Informe a senha atual e a nova (mínimo 8 caracteres). Troque a senha inicial no primeiro acesso.</p>
          </div>
        </div>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-senha.jpg" alt="Trocar senha">
          <figcaption>O card de troca de senha fica no final da aba Diário.</figcaption>
        </figure>
        <div class="tut-tip">⚠️ Após <b>5 tentativas erradas</b> de login, o acesso fica bloqueado por 15 minutos — proteção contra invasões. A sessão expira sozinha após 8&nbsp;horas sem uso.</div>
      </section>

      <?php if ($capable): ?>
      <!-- ═══════════ PONTO ═══════════ -->
      <section class="tut-section" id="ponto">
        <img class="tut-hero" src="<?= $T ?>/hero-jornada.jpg" alt="Ponto do dia">
        <p>O dia de trabalho começa e termina pelo <b>ponto</b>: os botões <b>▶ Iniciar jornada</b> e <b>⏹ Encerrar jornada</b>, na barra do topo do painel. É esse registro de entrada e saída que fecha a <b>folha de ponto do RH</b>.</p>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-ponto.jpg" alt="Barra de ponto do dia">
          <figcaption>Sem o ponto do dia, a barra fica vermelha e o botão <b>Iniciar jornada</b> (destacado) libera o trabalho.</figcaption>
        </figure>
        <ul>
          <li><b>Tudo fica bloqueado sem o ponto</b> — o sistema recusa propor atividades e usar os botões das etapas (Iniciar/Pausar/Concluir/Editar) enquanto a jornada do dia não for iniciada, e também depois de encerrada.</li>
          <li><b>Iniciar jornada</b> grava a entrada com a hora atual; a barra fica verde enquanto a jornada está aberta.</li>
          <li><b>Encerrar jornada</b> grava a saída e fecha o ponto do dia — depois disso, novos registros de trabalho do dia são recusados.</li>
          <li><b>Esqueceu de encerrar?</b> O sistema fecha o ponto automaticamente no <b>fim da jornada prevista</b> que você cadastrou na aba Jornada (o registro fica marcado como <i>auto</i> e com ¹ na folha de ponto).</li>
          <li><b>Dia de afastamento médico</b> não aceita ponto — o dia inteiro fica bloqueado.</li>
        </ul>
        <h3>Corrigir, completar ou registrar um dia esquecido</h3>
        <p>Na aba <b>🕐 Jornada</b>, o card <b>"⏱ Registro de ponto (folha do RH)"</b> mostra o ponto do <b>mês escolhido</b> e permite:</p>
        <ul>
          <li><b>Corrigir</b> a entrada/saída de um dia — o botão preenche o formulário; ajuste os horários e clique em <b>Salvar ponto</b>.</li>
          <li><b>Registrar um dia esquecido</b> — informe data, entrada e saída e clique em Salvar ponto.</li>
          <li><b>⟳ Gerar ponto pelos apontamentos</b> — completa de uma vez todos os dias do mês que <b>têm atividades apontadas mas não têm ponto</b>, usando o primeiro início real e o último término real do dia. Ideal para fechar meses anteriores à adoção do ponto. Dias que já têm ponto <b>não são alterados</b>; depois é só conferir e ajustar o que precisar.</li>
        </ul>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-ponto-corrigir.jpg" alt="Correção do ponto">
          <figcaption>O card de registro de ponto: seletor de mês, botão de gerar pelos apontamentos e a lista dos dias (a etiqueta <i>auto</i> indica encerramento automático).</figcaption>
        </figure>
        <div class="tut-tip">💡 Na folha de ponto, um dia trabalhado que ainda não tem ponto registrado aparece com os horários deduzidos dos apontamentos e o marcador <b>²</b> — gere o ponto do mês para oficializar esses horários.</div>
        <div class="tut-tip">💡 No Diário, cada dia mostra a etiqueta <b>⏱ Ponto</b> com a entrada e a saída registradas — fácil de conferir com a jornada prevista ao lado.</div>
      </section>

      <!-- ═══════════ REGISTRAR ═══════════ -->
      <section class="tut-section" id="registrar">
        <img class="tut-hero" src="<?= $T ?>/hero-registrar.jpg" alt="Registrar atividades">
        <p>Na aba <b>✏️ Registrar</b> você propõe as atividades do dia. O grande diferencial: você informa só o essencial e o <b>sistema preenche a previsão de cada etapa automaticamente</b>. Lembre-se: é preciso ter <b>iniciado a jornada</b> (ponto do dia) para registrar.</p>
        <ol class="tut-steps">
          <li>Preencha o <b>título</b> (obrigatório), a categoria e a descrição.</li>
          <li>Informe a <b>data</b>, o <b>horário de início</b> e a <b>duração estimada</b> em minutos.</li>
          <li>Confira as <b>etapas</b>: elas vêm do seu modelo de trabalho (nome e peso % de cada uma) e podem ser ajustadas para esta atividade — adicione, remova ou renomeie.</li>
          <li>Veja a <b>prévia da previsão</b> ao lado do botão: o sistema distribui a duração pelos pesos e mostra o horário previsto de cada etapa.</li>
          <li>Clique em <b>Registrar atividade</b>.</li>
        </ol>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-registrar.jpg" alt="Formulário de nova atividade">
          <figcaption>A prévia (destacada) mostra o horário previsto de cada etapa antes de salvar — e já desvia dos descansos registrados no dia.</figcaption>
        </figure>
        <div class="tut-tip">💡 Registre o <b>almoço antes</b> de propor as atividades do dia: a previsão das etapas pula automaticamente o intervalo (ex.: atividade de 4h iniciada às 10h com almoço 12h–13h termina às 15h).</div>
      </section>

      <!-- ═══════════ INTERVALOS ═══════════ -->
      <section class="tut-section" id="intervalos">
        <img class="tut-hero" src="<?= $T ?>/hero-intervalos.jpg" alt="Descansos e saúde">
        <p>Ainda na aba Registrar há dois cards separados: <b>"Registrar descanso"</b> (☕ almoço e 🍽 janta) e <b>"🏥 Saúde"</b> (saída médica e afastamento). Os registros de saúde ficam <b>separados dos descansos</b>, com indicador próprio no painel.</p>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-descanso.jpg" alt="Registro de descanso">
          <figcaption>Descanso: escolha o tipo, a data e o intervalo. Para excluir, use o ✕ na etiqueta do Diário.</figcaption>
        </figure>
        <h3>Saída médica e afastamento</h3>
        <ul>
          <li><b>Saída médica</b> — informe a data e o horário de <b>saída</b>; o <b>retorno é opcional</b>. Sem retorno, o sistema entende que você não voltou e conta as horas restantes <b>da sua jornada</b> como usadas pela saída.</li>
          <li><b>Afastamento médico</b> — informe a data inicial e a final (<b>1 dia ou mais</b>); os dias inteiros ficam bloqueados para apontamentos.</li>
          <li><b>Atestado médico</b> — anexe o arquivo (PDF/JPG/PNG, até 5&nbsp;MB) para ficar registrado; ele fica guardado com acesso restrito e pode ser baixado pela etiqueta 📎 no Diário (você, a gestão e a administração).</li>
        </ul>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-saude.jpg" alt="Registro de saúde">
          <figcaption>O card de Saúde: o formulário muda conforme o tipo (saída com horários; afastamento com período em dias).</figcaption>
        </figure>
        <h3>Anexar o atestado depois</h3>
        <p>Como o atestado só fica em mãos <b>ao fim do atendimento</b>, você pode registrar a saída médica na hora e anexar o documento mais tarde: no <b>Diário</b>, na etiqueta do registro de saúde, clique em <b>📎 anexar atestado</b> e escolha o arquivo. Se já houver um anexado, o botão <b>↻</b> substitui (o arquivo antigo é apagado).</p>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-atestado.jpg" alt="Anexar atestado depois">
          <figcaption>A etiqueta da saída médica no Diário, com o botão de anexar o atestado a qualquer momento.</figcaption>
        </figure>
        <div class="tut-tip">💡 Assim que o atestado é anexado, os relatórios passam a mostrar <b>"atestado entregue"</b> no lugar de "sem atestado" — sem precisar refazer o registro.</div>
        <p>Descansos e saídas médicas são <b>janelas protegidas</b>:</p>
        <ul>
          <li>a previsão de novas atividades <b>pula</b> o período;</li>
          <li><b>nenhum registro é aceito</b> dentro dele — botões e edição manual são recusados com aviso (afastamento bloqueia o dia inteiro);</li>
          <li>o tempo é <b>descontado</b> das horas de trabalho — no caso da saúde, desconta-se <b>apenas o que era horário de jornada</b>, nunca o tempo total fora;</li>
          <li>descansos do mesmo dia não podem se sobrepor.</li>
        </ul>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-chips.jpg" alt="Etiquetas do dia">
          <figcaption>No Diário, cada dia mostra a jornada, os descansos e os registros de saúde como etiquetas.</figcaption>
        </figure>
      </section>
      <?php endif; ?>

      <!-- ═══════════ DIÁRIO ═══════════ -->
      <section class="tut-section" id="diario">
        <img class="tut-hero" src="<?= $T ?>/hero-diario.jpg" alt="Diário">
        <p>A aba <b>📒 Diário</b> é o centro do sistema: os indicadores do período, os filtros e a lista de atividades por dia, cada uma com suas etapas e horários <b>previstos × reais</b>.</p>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-diario.jpg" alt="Visão geral do Diário">
          <figcaption>Indicadores (atividades, concluídas, em andamento, horas líquidas, descanso e dias), filtros e a lista do período.</figcaption>
        </figure>
        <h3>Filtros e exportação</h3>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-filtros.jpg" alt="Filtros do diário">
          <figcaption>Períodos rápidos (Hoje/Semana/Mês/Tudo), datas livres, busca por texto e exportação XLS<?= $role !== 'professor' ? ' — e o seletor de professor(a) para a consulta' : '' ?>.</figcaption>
        </figure>
        <?php if ($capable): ?>
        <h3>Trabalhando nas etapas</h3>
        <p>Cada etapa tem botões conforme o estado — os horários reais são gravados automaticamente no clique:</p>
        <ul>
          <li><b>Iniciar</b> → marca o início real da etapa;</li>
          <li><b>Pausar</b> → congela a contagem para você atender outra coisa;</li>
          <li><b>Retomar</b> → continua de onde parou (o tempo pausado <b>não conta</b> como trabalho);</li>
          <li><b>Concluir</b> → marca o término real (se estiver pausada, encerra a pausa junto);</li>
          <li><b>Editar</b> → ajusta os horários manualmente; <b>Refazer</b> → limpa horários e pausas.</li>
        </ul>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-etapas.jpg" alt="Botões das etapas">
          <figcaption>Atividade em andamento: a etapa ativa mostra Pausar e Concluir (destacados).</figcaption>
        </figure>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-pausada.jpg" alt="Atividade pausada">
          <figcaption>Atividade pausada: borda azul, selo "⏸ em pausa" e o botão Retomar (destacado). Use para alternar entre atividades.</figcaption>
        </figure>
        <div class="tut-tip">💡 O card da atividade tem os botões <b>Editar</b> (título, categoria, descrição) e <b>Excluir</b> (remove a atividade e todas as etapas).</div>
        <?php else: ?>
        <div class="tut-tip">👁 O perfil de gestão é <b>somente consulta</b>: escolha o professor no seletor, filtre o período e acompanhe — sem risco de alterar nada.</div>
        <?php endif; ?>
      </section>

      <?php if ($capable): ?>
      <!-- ═══════════ JORNADA ═══════════ -->
      <section class="tut-section" id="jornada">
        <img class="tut-hero" src="<?= $T ?>/hero-jornada.jpg" alt="Jornada e banco de horas">
        <p>Na aba <b>🕐 Jornada</b> você cadastra sua semana de trabalho: marque os dias e informe <b>entrada e saída</b> de cada um (ex.: segunda a sexta, 07:00 às 14:40). Salve tudo de uma vez.</p>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-jornada.jpg" alt="Jornada semanal e banco de horas">
          <figcaption>A semana completa e, abaixo, o extrato do banco de horas com o saldo.</figcaption>
        </figure>
        <h3>Avisos automáticos</h3>
        <p>Com a jornada cadastrada, o sistema vigia o relógio para você (a verificação roda a cada minuto):</p>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-aviso.jpg" alt="Aviso de fim de jornada">
          <figcaption>Faltando 30 minutos para o fim do expediente, o aviso amarelo aparece no topo de todas as abas.</figcaption>
        </figure>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-banco.jpg" alt="Registro no banco de horas">
          <figcaption>Passou do horário? O banner laranja mostra o tempo excedido e o botão (destacado) para creditar as horas no banco.</figcaption>
        </figure>
        <p>O crédito é calculado pelo servidor a partir dos seus <b>apontamentos reais</b> feitos após o fim da jornada, já descontando intervalos e pausas. É <b>um registro por dia</b>: se continuar trabalhando e clicar de novo, o valor é atualizado. O extrato permite excluir um crédito registrado por engano.</p>
      </section>
      <?php endif; ?>

      <!-- ═══════════ RELATÓRIOS ═══════════ -->
      <section class="tut-section" id="relatorios">
        <img class="tut-hero" src="<?= $T ?>/hero-relatorios.jpg" alt="Relatórios">
        <p>Na aba <b>🖨 Relatórios</b>, escolha <?= $role !== 'professor' ? 'o professor, ' : '' ?>o período e o tipo de relatório, e clique em <b>Gerar relatório</b>. A prévia aparece como um documento em fundo branco, pronto para impressão. O seletor <b>Mês</b> preenche De/Até com o mês inteiro de uma vez — ideal para o fechamento mensal — e vale para os três tipos.</p>
        <ul>
          <li><b>Simplificado</b> — uma linha por dia com <b>início e término do trabalho apontado</b>, os intervalos e as horas trabalhadas líquidas. Dias com <code>*</code> têm etapas ainda sem registro real.</li>
          <li><b>Detalhado</b> — todas as atividades e etapas do período, com previsão × real e as pausas.</li>
          <li><b>Folha de ponto</b> — o modelo para o <b>fechamento do RH</b>: entrada e saída registradas pelos botões de jornada, os intervalos do dia e as horas do ponto (entrada → saída, descontados descansos e saídas médicas). Saídas automáticas aparecem com ¹.</li>
        </ul>
        <p>Todos saem com os <b>campos de assinatura</b> — <?= e(DIRECTOR_NAME) ?> (<?= e(DIRECTOR_ROLE) ?>) e o professor (nome + RM) — na tela, na impressão e no PDF.</p>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-rel-ponto.jpg" alt="Folha de ponto">
          <figcaption>Folha de ponto do mês: escolha o tipo, o mês no seletor e clique em Gerar relatório.</figcaption>
        </figure>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-rel-simplificado.jpg" alt="Relatório simplificado">
          <figcaption>Relatório simplificado gerado; os botões Imprimir e PDF (destacados) são liberados após gerar.</figcaption>
        </figure>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-rel-detalhado.jpg" alt="Relatório detalhado">
          <figcaption>O detalhado lista cada atividade com a tabela de etapas.</figcaption>
        </figure>
        <div class="tut-tip">💡 O botão <b>⬇ XLS</b> fica na aba Diário e exporta a planilha completa (uma linha por etapa, com professor, RM, pausas e durações líquidas).</div>
      </section>

      <?php if ($admin): ?>
      <!-- ═══════════ CONTAS ═══════════ -->
      <section class="tut-section" id="contas">
        <img class="tut-hero" src="<?= $T ?>/hero-contas.jpg" alt="Contas da equipe">
        <p>A aba <b>👥 Contas</b> é exclusiva do administrador. É aqui que você cria os acessos dos professores:</p>
        <ol class="tut-steps">
          <li>Preencha <b>nome completo</b>, <b>RM (matrícula)</b>, o <b>usuário de acesso</b> e a <b>senha inicial</b> (mínimo 8 caracteres) — combine com a pessoa que ela troque no primeiro login.</li>
          <li>Defina as <b>etapas de trabalho</b> da função (quantidade, nomes e pesos %). Elas passam a preencher automaticamente o formulário de atividades daquele professor.</li>
          <li>Clique em <b>Criar conta</b>.</li>
        </ol>
        <figure class="tut-img">
          <img src="<?= $T ?>/tela-contas.jpg" alt="Gestão de contas">
          <figcaption>Formulário de nova conta com o editor de etapas (destacado) e, abaixo, a lista de contas com Editar e Desativar/Reativar.</figcaption>
        </figure>
        <div class="tut-tip">💡 <b>Editar</b> permite ajustar nome, RM, etapas e redefinir a senha de qualquer conta (deixe a senha vazia para manter). <b>Desativar</b> bloqueia o login sem apagar o histórico — e você não consegue desativar a própria conta.</div>
      </section>
      <?php endif; ?>

      <!-- ═══════════ DICAS ═══════════ -->
      <section class="tut-section" id="dicas">
        <div class="panel-card">
          <div class="panel-title">💡 Dicas rápidas</div>
          <ul class="tut-dicas">
            <?php if ($capable): ?>
            <li><b>Rotina sugerida do dia:</b> clique em <b>▶ Iniciar jornada</b> → registre o almoço previsto → proponha as atividades → use Iniciar/Concluir em cada etapa conforme trabalha → ao fim do expediente, clique em <b>⏹ Encerrar jornada</b> (e registre eventuais horas extras no banco de horas).</li>
            <li>Precisou atender outra demanda? <b>Pause</b> a etapa atual, registre e trabalhe na outra atividade, depois <b>Retome</b> — os tempos ficam corretos sozinhos.</li>
            <li>Esqueceu de apontar em tempo real? Use <b>Editar</b> na etapa e informe os horários manualmente (o sistema recusa horários dentro de intervalos).</li>
            <?php endif; ?>
            <li>Os indicadores e relatórios mostram sempre <b>horas líquidas</b>: descansos, saídas médicas e pausas já descontados.</li>
            <li>O rodapé de cada relatório indica quando ele foi emitido — imprima ou gere o PDF na hora de coletar as assinaturas.</li>
            <?php if ($admin): ?>
            <li>O rodapé do sistema mostra (só para você) qual banco de dados está em uso — útil ao conferir o phpMyAdmin.</li>
            <?php endif; ?>
          </ul>
        </div>
      </section>
    </div>
  </main>
