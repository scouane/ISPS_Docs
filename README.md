# ISPS Flow — Protótipo

Sistema Digital de Submissão, Tramitação, Despacho e Gestão de Documentação
Administrativa do Instituto Superior Politécnico de Songo.

## O que este protótipo já faz

- **Autenticação** por perfil (Estudante, Secretaria, Director, Administrador)
- **Submissão de requerimentos** pelo estudante, com anexos e geração automática
  de número de protocolo (`ISPS-2026-000123`)
- **Motor de Tramitação**: calcula automaticamente o destino do processo
  (Direcção Geral / Director de Curso / Direcção Central) com base no tipo de
  pedido e no curso do estudante, usando a tabela `routing_rules`
- **Triagem** pela Secretaria (confirma e liberta o processo para o destinatário)
- **Despacho** pelo Director (decisão + fundamentação), com geração automática
  do registo de Comunicação e notificação ao estudante
- **Histórico completo** de cada processo (`request_history`) visível pelo estudante
- **Notificações** internas por utilizador
- **Painel do Administrador**: estatísticas gerais, gestão de utilizadores
  (incluindo associação de directores a cursos/direcções e registo de
  estudantes), gestão de tipos de pedido, gestão de destinos institucionais
  e gestão de regras de encaminhamento (`routing_rules`)
- **Geração automática do PDF da Comunicação de Despacho**: ao director
  emitir o despacho, o sistema gera de imediato um PDF formal (protocolo,
  estudante, curso, decisão, fundamentação, responsável e data) usando um
  gerador de PDF em PHP puro (`includes/simple_pdf.php`, sem dependências
  externas), regista-o em `documents` e liga-o à `communications`
- **Download seguro de documentos** (`download_document.php`): serve
  anexos e comunicações verificando que o estudante só acede aos
  documentos dos seus próprios processos; secretaria/director/admin têm
  acesso alargado

## O que falta (próximos passos sugeridos)

- Substituir `SimplePdf` por `dompdf`/`mPDF` (via Composer) se quiseres um
  layout com o template institucional oficial (logótipo, cabeçalho, fontes
  personalizadas) em vez do documento funcional actual
- Validação/força de password, recuperação de password
- Paginação e filtros nas listagens (quando o volume crescer)

## Instalação (XAMPP)

1. Copia a pasta `isps_flow/` para `htdocs/` do XAMPP.
2. Abre o phpMyAdmin e importa `database/schema.sql` (cria a BD `isps_flow`
   com todas as tabelas, cursos, direcções e alguns tipos de pedido de exemplo).
3. Confirma as credenciais em `config/database.php` (por omissão, `root` sem password).
4. Cria dados de teste correndo, na pasta do projecto:
   ```
   php scripts/seed_users.php
   ```
   Isto cria um utilizador de cada perfil (password `Teste123!` para todos)
   e associa o Director de teste ao curso EET.
5. Acede a `http://localhost/isps_flow/public/login.php`.

## Estrutura de pastas

```
isps_flow/
├── config/database.php        Ligação PDO à base de dados
├── includes/
│   ├── auth.php                Sessão, login, criação de utilizadores
│   ├── protocol_generator.php  Geração atómica do número de protocolo
│   ├── routing_engine.php      Motor de tramitação + histórico + notificações
│   ├── simple_pdf.php          Gerador de PDF em PHP puro (sem dependências)
│   └── communication_generator.php  Gera o PDF da Comunicação de Despacho
├── public/
│   ├── login.php / logout.php
│   ├── estudante/               Submissão e acompanhamento
│   ├── secretaria/              Triagem
│   ├── director/                Análise e despacho
│   └── admin/                   Utilizadores, tipos de pedido, destinos, regras
├── database/schema.sql
├── scripts/seed_users.php
└── assets/css/style.css
```

## Testar o fluxo completo

1. Login como `estudante@isps.ac.mz` → submeter um "Requerimento Académico
   Geral" → o motor de tramitação encaminha automaticamente para o Director
   do curso EET.
2. Login como `secretaria@isps.ac.mz` → ver o processo em Triagem → clicar
   "Encaminhar".
3. Login como `director.eet@isps.ac.mz` → ver o processo em "Processos para
   Análise" → emitir despacho (Deferido/Indeferido + fundamentação).
4. Login novamente como estudante → o processo aparece como "Comunicado",
   com o histórico completo visível e a Comunicação de Despacho disponível
   para download em PDF na lista de documentos.
