# Diário de Bordo — CECAPE

Registro de atividades com **previsão automática das fases**: o administrador propõe a
atividade (título, início e duração) e o sistema calcula o **início e o término previstos de
cada fase**, respeitando a jornada de trabalho configurada. Depois, cada fase pode ter o
**início e o término reais** registrados, permitindo comparar previsto × realizado.

A aplicação é 100% estática — HTML, CSS e JavaScript, **sem nenhuma dependência externa**.
Basta abrir o `index.html` no navegador.

---

## Dois perfis de acesso

| | **Administrador** | **Usuário (visualização)** |
|---|---|---|
| Ver painel e registros | ✅ | ✅ |
| Exportar XLS / PDF / imprimir | ✅ | ✅ |
| Criar, editar, duplicar e excluir atividades | ✅ | — |
| Registrar início/término reais das fases | ✅ | — |
| Replanejar as fases pendentes | ✅ | — |
| Configurar jornada, identidade e backup | ✅ | — |

O modo administrador é protegido por um PIN (**inicial: `1234`**, alterável em *Configurações*).
A aplicação abre sempre em modo visualização.

> **Sobre a proteção do PIN:** o controle é local, feito no próprio navegador. Ele evita
> alterações acidentais por quem só vai consultar, mas **não é autenticação de servidor** —
> quem tiver acesso ao computador e conhecimento técnico consegue contorná-lo. Para controle
> de acesso real, seria necessário um backend com autenticação.

---

## Como usar

### Abrir

Duplo clique em `index.html` — ou, para servir por HTTP:

```bash
npm start        # http://localhost:8123
```

### Registrar uma atividade (admin)

1. **Entrar como admin** → PIN `1234`.
2. Aba **Nova atividade**.
3. Preencha título, responsável, categoria, local e o **início previsto**.
4. Defina o término de uma destas formas:
   - **Duração total** — ex.: 4h; ou
   - **Término previsto** — o sistema calcula quantos minutos úteis existem no intervalo.
5. Escolha um **modelo de fases** (Padrão, Aula/Formação, Projeto, Reunião, Visita técnica ou
   Personalizado) e ajuste os pesos. As fases podem ser renomeadas, reordenadas, adicionadas
   e removidas.
6. A tabela **Previsão calculada** atualiza em tempo real, mostrando início, término e duração
   de cada fase. Salve.

### Acompanhar a execução (admin)

Abra a atividade e, em cada fase, use **Iniciar** / **Concluir** para carimbar o horário atual,
ou digite as datas manualmente nos campos de início/término reais. O sistema atualiza sozinho
o progresso, a situação e as durações totais da atividade.

**Replanejar restante** recalcula a previsão de todas as fases ainda não concluídas a partir
de agora — útil quando a atividade atrasou.

### Consultar e exportar (qualquer perfil)

Na aba **Registros**, filtre por texto, período, responsável, categoria e situação. Os três
botões de exportação sempre respeitam os filtros aplicados:

- **Exportar XLS** — planilha Excel com três abas: `Atividades`, `Fases` e `Informações`
  (metadados do relatório). Datas saem como data/hora reais, prontas para filtro e cálculo.
- **Exportar PDF** — relatório em A4 paisagem, com cabeçalho, uma seção por atividade,
  tabela de fases e numeração de páginas. Gerado nativamente, sem biblioteca externa.
- **Imprimir** — abre uma versão limpa do relatório e chama a impressão do navegador
  (que também permite “Salvar como PDF”).

O botão **Abrir** de cada linha mostra o detalhe completo, com linha do tempo das fases; ali
também é possível exportar/imprimir apenas aquela atividade.

---

## Jornada de trabalho

Todo o cálculo de previsão usa a jornada definida em **Configurações**:

- **Horário comercial** — dias úteis, início e fim do expediente e intervalo de almoço
  opcional (padrão: seg–sex, 08:00–18:00, pausa 12:00–13:00);
- **Contínuo (24h)** — sem interrupções, para plantões e atividades ininterruptas.

As durações são sempre **tempo útil**. Uma atividade de 6h que começa às 10:00 termina às
17:00 no mesmo dia, porque a hora do almoço não é contabilizada; o que não couber no
expediente transborda para o próximo dia útil.

## Situações

| Situação | Quando aparece |
|---|---|
| **Planejada** | ainda não iniciada e dentro do prazo |
| **Em andamento** | pelo menos uma fase iniciada, prazo não vencido |
| **Atrasada** | término previsto já passou e a atividade não foi concluída |
| **Concluída** | todas as fases com término real registrado |
| **Cancelada** | marcada manualmente pelo administrador |

---

## Dados e backup

Os registros ficam no **`localStorage` do navegador**, no computador onde foram criados — não
há servidor. Consequências práticas:

- limpar os dados de navegação apaga os registros;
- cada navegador/computador tem sua própria base;
- para levar os dados a outra máquina, use **Configurações → Backup dos dados**
  (exporta um `.json` e importa substituindo ou acrescentando aos registros existentes).

Na primeira execução, quatro atividades de exemplo são criadas para ilustrar as situações.
Use *Apagar todos os registros* para começar do zero.

---

## Estrutura do projeto

```
index.html                  estrutura das telas (painel, registros, formulário, configurações)
assets/css/style.css        design system: tokens, tema claro/escuro, componentes, impressão
assets/js/util.js           datas, formatação pt-BR, helpers de DOM
assets/js/schedule.js       motor de previsão: jornada, minutos úteis e distribuição das fases
assets/js/store.js          persistência, CRUD, regras de situação/progresso e filtros
assets/js/pdf.js            gerador de PDF (Helvetica/WinAnsi, múltiplas páginas)
assets/js/export.js         exportação XLS (SpreadsheetML), relatório PDF e versão de impressão
assets/js/app.js            interface: navegação, perfis, formulário e ações
tests/                      testes automatizados
```

### O motor de previsão (`schedule.js`)

- `janelasDoDia(data, jornada)` — janelas de trabalho do dia, em minutos.
- `proximoInstanteUtil(data, jornada)` — avança para o próximo momento válido da jornada.
- `somarMinutosUteis(data, minutos, jornada)` — soma tempo útil pulando noites, fins de
  semana e intervalos.
- `minutosUteisEntre(a, b, jornada)` — tempo útil entre dois instantes.
- `planejar({ inicio, duracaoMin, fases, distribuir, jornada })` — rateia a duração entre as
  fases (por peso ou por duração fixa) e devolve início/término previstos de cada uma,
  encadeadas sem lacunas. O arredondamento sobra na última fase, de modo que a soma das
  fases é sempre exatamente a duração informada.

---

## Testes

```bash
npm test                 # 36 testes do motor de previsão e de formatação (só Node)
```

Teste de interface ponta a ponta (opcional, requer Playwright):

```bash
npm i -D playwright
npm start                    # em um terminal
npm run test:interface       # em outro — 33 verificações + capturas em tests/.saida/
npm run test:pdf             # 26 verificações estruturais do PDF gerado
```

O teste de interface cobre o fluxo completo: painel, filtros, downloads de XLS e PDF, acesso
por PIN, criação de atividade com previsão automática, edição de fases, registro de execução,
replanejamento, persistência após recarregar, tema escuro e layout responsivo.

## Compatibilidade

Navegadores modernos (Chrome, Edge, Firefox e Safari atuais). Usa `color-mix()` e o seletor
`:has()` para detalhes visuais — em navegadores antigos a interface continua funcional, apenas
com menos refinamento. Funciona offline, inclusive abrindo o arquivo diretamente do disco.
