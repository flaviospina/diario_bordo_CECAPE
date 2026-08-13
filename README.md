# Diário de Bordo · CECAPE

Diário de bordo para registro das atividades executadas em home office, com dia e horário de início e término de cada atividade e de cada fase. Serve como controle dos projetos executados durante o período de trabalho remoto, permitindo que a direção acompanhe o andamento em modo de consulta.

## Como funciona

O sistema tem **dois modos de acesso**:

| Modo | Página | Quem usa | O que pode fazer |
|---|---|---|---|
| **Administrador** | `admin.php` | Somente o responsável pelo diário (protegido por senha) | Propor atividades, iniciar/concluir fases, editar e excluir registros |
| **Consulta** | `index.php` | Diretor e demais interessados (sem senha) | Visualizar, filtrar, exportar XLS e PDF e imprimir |

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
- Exportação para **XLS** (planilha com uma linha por fase), **PDF** (relatório em paisagem) e **impressão** com layout próprio.

## Tecnologia

- PHP 8+ com SQLite (arquivo único em `data/diario.sqlite`, criado automaticamente no primeiro acesso) — roda em qualquer hospedagem compartilhada (HostGator etc.), sem instalação de banco de dados.
- Front-end sem framework, layout moderno e minimalista.
- Bibliotecas de exportação (SheetJS e jsPDF) carregadas por CDN, com alternativa embutida caso o CDN esteja indisponível.

## Instalação

1. Envie todos os arquivos para uma pasta do servidor (ex.: `public_html/diario`).
2. Acesse `https://seusite/diario/admin.php` e entre com a senha padrão: **cecape2026**.
3. **Troque a senha**: acesse `gerar_senha.php`, gere o hash da nova senha, cole em `ADMIN_PASSWORD_HASH` no `config.php` e depois **exclua o arquivo `gerar_senha.php` do servidor**.
4. Compartilhe com a direção apenas o endereço `https://seusite/diario/` (modo consulta).

Para testar localmente:

```bash
php -S localhost:8000
# abra http://localhost:8000/admin.php
```

## Estrutura

```
config.php        Configurações (senha, fases padrão, fuso horário)
db.php            Conexão e criação do banco SQLite
api.php           API JSON (leitura pública, escrita restrita ao admin)
index.php         Modo consulta (diretor)
admin.php         Modo administrador (registro das atividades)
gerar_senha.php   Utilitário para trocar a senha (excluir após uso)
assets/           CSS e JavaScript
data/             Banco SQLite (protegido contra acesso direto)
```
