Sim. A melhor interface é um **construtor visual em etapas**, parecido com o WhatsApp Manager, mas mais simples. O usuário não deve ver JSON, endpoint, `components`, `{{1}}` técnico ou payload. Ele deve ver: **categoria, nome, idioma, conteúdo, variáveis, botões, prévia e enviar para aprovação**.

A Meta estrutura templates com estes blocos: **header, body, footer e buttons**. O body é o conteúdo principal e é obrigatório; header, footer e botões são opcionais. ([Facebook Developers][1])

---

# Estrutura ideal da interface

## Tela principal: “Modelos de Mensagem”

Crie uma tela com tabela:

| Nome                |    Categoria | Idioma |   Status | Última atualização | Ações                   |
| ------------------- | -----------: | -----: | -------: | -----------------: | ----------------------- |
| confirmacao_pedido  |   Utilitário |  pt_BR | Aprovado |               hoje | Ver / Duplicar / Testar |
| oferta_black_friday |    Marketing |  pt_BR | Pendente |               hoje | Ver                     |
| codigo_login        | Autenticação |  pt_BR | Aprovado |              ontem | Testar                  |

Filtros:

```txt
Status: Todos / Aprovado / Pendente / Rejeitado / Pausado
Categoria: Marketing / Utilitário / Autenticação
Idioma: pt_BR / en_US / es_ES
Busca por nome
```

Botões:

```txt
+ Criar modelo
Sincronizar com Meta
```

Por baixo, o botão “Sincronizar com Meta” chama:

```txt
GET /{WABA_ID}/message_templates
```

A API oficial de templates permite listar modelos de uma WhatsApp Business Account usando o endpoint de templates da WABA. ([Facebook Developers][2])

---

# Fluxo de criação em 7 etapas

## Etapa 1 — Escolher o objetivo

Em vez de começar com “categoria”, comece com uma pergunta humana:

```txt
Qual é o objetivo desta mensagem?
```

Cards:

```txt
Atualizar cliente sobre pedido, pagamento, entrega ou agendamento
→ Categoria sugerida: Utilitário

Enviar oferta, campanha, lançamento ou recuperação de carrinho
→ Categoria sugerida: Marketing

Enviar código de verificação ou login
→ Categoria sugerida: Autenticação
```

A Meta exige que cada template seja categorizado como **authentication**, **marketing** ou **utility**. ([Facebook Developers][3])

Também vale deixar um aviso:

```txt
Se a mensagem tiver tom promocional, a Meta pode classificar como Marketing mesmo que você escolha Utilitário.
```

A Meta informa que, desde 9 de abril de 2025, se o usuário escolher `UTILITY`, mas o WhatsApp determinar que o conteúdo é `MARKETING`, o template pode ser aprovado como marketing. ([Facebook Developers][4])

---

## Etapa 2 — Nome interno do template

Campo visível:

```txt
Nome interno do modelo
```

Ajuda:

```txt
Use um nome simples, sem espaços. Exemplo: confirmacao_pedido, lembrete_agendamento, oferta_cliente_vip
```

Validação automática:

```txt
Apenas letras minúsculas, números e underline
Máximo de 512 caracteres
Sem espaços
Sem acentos
Sem hífen
```

A Meta limita nomes de templates a 512 caracteres e permite apenas caracteres alfanuméricos minúsculos e underscores. ([Facebook Developers][3])

No frontend, quando o usuário digitar:

```txt
Confirmação Pedido
```

Você transforma automaticamente em:

```txt
confirmacao_pedido
```

Isso reduz erro e fricção.

---

## Etapa 3 — Idioma

Campo:

```txt
Idioma da mensagem
```

Opções mais usadas:

```txt
Português do Brasil — pt_BR
Inglês — en_US
Espanhol — es_ES
```

Regra importante:

```txt
Cada idioma precisa ser enviado como uma versão própria do template.
```

A interface pode ter um botão:

```txt
+ Adicionar outro idioma
```

Mas para o MVP, eu faria primeiro apenas **um idioma por criação**.

---

## Etapa 4 — Cabeçalho

Mostre como bloco opcional:

```txt
Cabeçalho
Adicionar destaque no topo da mensagem
```

Opções:

```txt
Nenhum
Texto
Imagem
Vídeo
Documento
Localização
```

A documentação de componentes da Meta mostra que templates podem usar header de texto ou mídia, e também menciona que headers de localização só podem ser usados em templates Utility ou Marketing. ([Facebook Developers][1])

### Se o usuário escolher “Texto”

Campo:

```txt
Título da mensagem
```

Exemplo:

```txt
Pedido confirmado
```

Validações:

```txt
Máximo 60 caracteres
No máximo 1 variável
```

### Se escolher imagem, vídeo ou documento

Mostre upload:

```txt
Enviar arquivo de exemplo para aprovação
```

Importante: para criar template com mídia no cabeçalho, a Meta exige obter um asset handle usando a **Resumable Upload API**. ([Facebook Developers][3])

Então a UI deve esconder isso assim:

```txt
Upload do exemplo
↓
Backend envia para Meta Resumable Upload API
↓
Backend recebe header_handle
↓
Backend coloca header_handle no payload do template
```

O usuário só vê:

```txt
Arquivo enviado com sucesso
```

---

## Etapa 5 — Corpo da mensagem

Esse é o coração da interface.

Campo grande:

```txt
Mensagem principal
```

Exemplo:

```txt
Olá, {{nome}}! Seu pedido {{numero_pedido}} foi confirmado e será enviado até {{data_envio}}.
```

Mas por baixo, a Meta trabalha com placeholders posicionais, como `{{1}}`, `{{2}}`, `{{3}}`. Muitas plataformas mostram a variável como nome amigável para o usuário e convertem internamente para o formato aceito. A documentação de parceiros mostra esse padrão de variáveis como `{{1}}`, `{{2}}`, etc. ([360dialog][5])

A experiência ideal:

### O usuário vê:

```txt
{{nome}}
{{numero_pedido}}
{{data_envio}}
```

### O backend envia para a Meta:

```txt
{{1}}
{{2}}
{{3}}
```

### O banco salva o mapa:

```json
{
  "1": "nome",
  "2": "numero_pedido",
  "3": "data_envio"
}
```

A interface deve ter botão:

```txt
+ Inserir variável
```

Ao clicar, abre um modal:

```txt
Nome da variável:
[ nome_do_cliente ]

Valor de exemplo:
[ Ana ]
```

Depois insere no texto:

```txt
{{nome_do_cliente}}
```

E cria automaticamente o exemplo obrigatório.

---

# Regra essencial para variáveis

Toda variável precisa ter **valor de exemplo**.

Exemplo:

| Variável      | Exemplo    |
| ------------- | ---------- |
| nome          | Ana        |
| numero_pedido | 12345      |
| data_envio    | 28/04/2026 |

Isso é importante porque a Meta usa exemplos para entender o conteúdo durante a revisão. A documentação de componentes mostra o uso de exemplos em templates com variáveis e mídia. ([Facebook Developers][1])

---

## Etapa 6 — Rodapé

Campo opcional:

```txt
Rodapé
```

Exemplos bons:

```txt
Responda SAIR para não receber mais mensagens.
Equipe Sua Empresa
```

Validações:

```txt
Máximo 60 caracteres
Sem variáveis
Texto simples
```

Fontes de documentação de templates geralmente apresentam footer como componente opcional de texto, com limite de 60 caracteres. ([CleverTap User Docs][6])

---

## Etapa 7 — Botões

Aqui a interface precisa ser muito visual.

Bloco:

```txt
Botões
Adicione ações rápidas para o cliente
```

Tipos:

```txt
Resposta rápida
Abrir site
Ligar
Copiar código
```

A Meta trata botões como componente próprio dentro de templates, e exemplos oficiais de marketing mostram botões de URL, telefone e resposta rápida. ([Facebook Developers][7])

### Resposta rápida

Campos:

```txt
Texto do botão
```

Exemplos:

```txt
Confirmar
Falar com atendente
Ver detalhes
```

### Abrir site

Campos:

```txt
Texto do botão
URL
```

Tipos:

```txt
URL fixa
URL dinâmica
```

Exemplo fixo:

```txt
https://seudominio.com/pedido
```

Exemplo dinâmico:

```txt
https://seudominio.com/pedido/{{codigo}}
```

Para botões de URL em templates, a documentação da Meta mostra suporte a variável em URL em alguns tipos de template e limite de 25 caracteres no texto do botão. ([Facebook Developers][8])

### Ligar

Campos:

```txt
Texto do botão
Número de telefone
```

A documentação de templates de marketing personalizados mostra botão de telefone com texto e número. ([Facebook Developers][7])

### Copiar código

Bom para cupom.

Campos:

```txt
Código de exemplo
```

Templates de cupom da Meta usam código promocional e mostram regra de nome de template em minúsculas, alfanuméricos e underscores. ([Facebook Developers][9])

---

# Prévia em tempo real

A lateral direita da tela deve mostrar um card simulando WhatsApp:

```txt
┌─────────────────────────┐
│ Pedido confirmado        │  ← Header
│                          │
│ Olá, Ana! Seu pedido     │
│ 12345 foi confirmado...  │  ← Body com exemplos
│                          │
│ Equipe Loja X            │  ← Footer
│                          │
│ [Ver pedido]             │  ← Botão
└─────────────────────────┘
```

Essa prévia deve ter dois modos:

```txt
Modo edição: mostra {{nome}}, {{pedido}}
Modo exemplo: mostra Ana, 12345
```

Esse detalhe é fundamental. O cliente entende o que escreveu, e você valida o que será enviado.

---

# Validador inteligente antes de enviar

Antes do botão “Enviar para aprovação”, crie uma etapa chamada:

```txt
Revisão de conformidade
```

Mostre checklist:

```txt
✅ Nome do template válido
✅ Categoria selecionada
✅ Idioma selecionado
✅ Corpo preenchido
✅ Variáveis com exemplos
✅ Rodapé dentro do limite
✅ Botões válidos
✅ Arquivo de cabeçalho enviado, se necessário
```

E alertas:

```txt
⚠️ Essa mensagem parece promocional. Recomendamos usar categoria Marketing.
⚠️ Seu template começa com variável. Adicione texto antes da variável.
⚠️ Existe variável sem exemplo.
⚠️ URL dinâmica precisa de exemplo.
```

Essa camada vai reduzir rejeições.

---

# Como o backend deve abstrair a Meta

O frontend nunca deve montar JSON oficial da Meta.

O frontend envia algo simples:

```json
{
  "name": "confirmacao_pedido",
  "category": "UTILITY",
  "language": "pt_BR",
  "header": {
    "type": "TEXT",
    "text": "Pedido confirmado"
  },
  "body": {
    "text": "Olá, {{nome}}! Seu pedido {{numero_pedido}} foi confirmado.",
    "variables": [
      {
        "key": "nome",
        "example": "Ana"
      },
      {
        "key": "numero_pedido",
        "example": "12345"
      }
    ]
  },
  "footer": {
    "text": "Equipe Loja X"
  },
  "buttons": [
    {
      "type": "URL",
      "text": "Ver pedido",
      "url": "https://seudominio.com/pedido/{{numero_pedido}}",
      "example": "12345"
    }
  ]
}
```

Aí o backend transforma para o payload oficial:

```json
{
  "name": "confirmacao_pedido",
  "category": "UTILITY",
  "language": "pt_BR",
  "components": [
    {
      "type": "HEADER",
      "format": "TEXT",
      "text": "Pedido confirmado"
    },
    {
      "type": "BODY",
      "text": "Olá, {{1}}! Seu pedido {{2}} foi confirmado.",
      "example": {
        "body_text": [["Ana", "12345"]]
      }
    },
    {
      "type": "FOOTER",
      "text": "Equipe Loja X"
    },
    {
      "type": "BUTTONS",
      "buttons": [
        {
          "type": "URL",
          "text": "Ver pedido",
          "url": "https://seudominio.com/pedido/{{1}}",
          "example": ["12345"]
        }
      ]
    }
  ]
}
```

O endpoint oficial de criação é:

```txt
POST /{WABA_ID}/message_templates
```

A referência da API de templates da WhatsApp Business Account mostra o uso de `/{WABA_ID}/message_templates` para criar e gerenciar templates. ([Facebook Developers][10])

---

# Classes que eu criaria no plugin

```txt
TemplateDraftService
TemplateVariableParser
TemplateValidationService
TemplatePayloadBuilder
TemplateMetaService
TemplateSyncService
TemplateStatusService
TemplatePreviewService
```

## Responsabilidade de cada uma

### `TemplateDraftService`

Cuida do rascunho no banco.

```txt
criar rascunho
salvar etapa atual
duplicar template
marcar como enviado
```

### `TemplateVariableParser`

Converte variáveis amigáveis em variáveis Meta.

```txt
{{nome}} → {{1}}
{{numero_pedido}} → {{2}}
```

Também salva:

```json
{
  "nome": 1,
  "numero_pedido": 2
}
```

### `TemplateValidationService`

Centraliza regras.

```txt
nome válido
limite de caracteres
categoria válida
idioma válido
variáveis com exemplo
botões válidos
header válido
footer válido
```

### `TemplatePayloadBuilder`

Gera o JSON final da Meta.

Nenhum controller deve montar `components`.

### `TemplateMetaService`

Chama a Meta usando `MetaApiClient`.

```txt
createTemplate()
listTemplates()
syncTemplates()
```

### `TemplateSyncService`

Atualiza status local com dados da Meta.

```txt
PENDING
APPROVED
REJECTED
PAUSED
DISABLED
```

A documentação de gerenciamento de templates da Meta também cita status e limitações de edição após aprovação, incluindo que templates aprovados podem ter restrições de edição em janelas de tempo. ([Facebook Developers][2])

---

# Banco recomendado para templates

Adicione ou ajuste sua tabela para comportar o builder visual:

```sql
CREATE TABLE wp_was_message_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  whatsapp_account_id BIGINT UNSIGNED NOT NULL,
  meta_template_id VARCHAR(190) DEFAULT NULL,
  name VARCHAR(190) NOT NULL,
  category VARCHAR(80) NOT NULL,
  language VARCHAR(20) NOT NULL DEFAULT 'pt_BR',
  status VARCHAR(50) DEFAULT 'draft',

  friendly_payload LONGTEXT DEFAULT NULL,
  meta_payload LONGTEXT DEFAULT NULL,
  variable_map LONGTEXT DEFAULT NULL,

  header_type VARCHAR(50) DEFAULT NULL,
  body_text LONGTEXT NOT NULL,
  footer_text TEXT DEFAULT NULL,
  buttons_json LONGTEXT DEFAULT NULL,

  rejection_reason TEXT DEFAULT NULL,
  submitted_at DATETIME DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL,
  rejected_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,

  PRIMARY KEY (id),
  UNIQUE KEY tenant_template_lang (tenant_id, name, language),
  KEY tenant_id (tenant_id),
  KEY status (status),
  KEY category (category)
);
```

O ponto mais importante é salvar dois payloads:

```txt
friendly_payload = o que o usuário criou na interface
meta_payload = o JSON oficial enviado para a Meta
```

Isso facilita debug, suporte e manutenção.

---

# Fluxo técnico completo

```txt
Usuário clica em Criar Modelo
↓
Escolhe objetivo/categoria
↓
Define nome e idioma
↓
Monta header/body/footer/buttons visualmente
↓
Insere variáveis amigáveis
↓
Preenche exemplos
↓
Preview renderiza mensagem
↓
TemplateValidationService valida
↓
TemplatePayloadBuilder converte para components da Meta
↓
TemplateMetaService chama POST /{WABA_ID}/message_templates
↓
Salva resposta da Meta
↓
Status fica PENDING
↓
TemplateSyncService consulta GET /{WABA_ID}/message_templates
↓
Atualiza para APPROVED ou REJECTED
```

---

# UI ideal em WordPress

Mesmo sendo WordPress, a tela deve parecer SaaS.

Eu faria em React dentro do admin ou numa página `/app/templates`.

Layout:

```txt
┌──────────────────────────────────────────────┐
│ Criar modelo                                 │
├───────────────────────┬──────────────────────┤
│ Etapas                │ Prévia WhatsApp       │
│                       │                      │
│ 1 Objetivo            │  ┌───────────────┐   │
│ 2 Nome e idioma       │  │ Preview       │   │
│ 3 Cabeçalho           │  └───────────────┘   │
│ 4 Mensagem            │                      │
│ 5 Botões              │ Checklist            │
│ 6 Revisão             │ ✅ Nome válido        │
│                       │ ✅ Corpo válido       │
└───────────────────────┴──────────────────────┘
```

---

# Regras de UX para facilitar para leigo

Use linguagem de negócio, não linguagem técnica.

Em vez de:

```txt
Components
BODY
HEADER
Placeholders
Payload
```

Use:

```txt
Estrutura da mensagem
Mensagem principal
Cabeçalho
Campos personalizados
Prévia
Enviar para aprovação
```

Em vez de:

```txt
{{1}}
```

Mostre:

```txt
{{nome_cliente}}
```

Em vez de:

```txt
example.body_text
```

Mostre:

```txt
Exemplo que a Meta usará para entender sua mensagem
```

---

# O que validar antes de deixar enviar

Checklist técnico:

```txt
Nome usa apenas a-z, 0-9 e _
Nome tem até 512 caracteres
Categoria é MARKETING, UTILITY ou AUTHENTICATION
Idioma preenchido
Body não está vazio
Body está dentro do limite configurado
Todas as variáveis possuem exemplo
Header de texto possui no máximo 1 variável
Footer não possui variável
Botões possuem texto
Botões de URL possuem URL válida
URL dinâmica possui exemplo
Botão de telefone possui número
Mídia tem asset handle quando header for imagem/vídeo/documento
```

---

# Sugestão de interface “matadora”

Crie um botão chamado:

```txt
Melhorar chance de aprovação
```

Ele abre uma análise simples:

```txt
Sua mensagem parece utilitária porque menciona pedido e entrega.
Não encontramos promessa exagerada.
Não encontramos desconto, cupom ou linguagem promocional.
As variáveis possuem contexto suficiente.
```

Ou:

```txt
Atenção: encontramos palavras como “promoção”, “desconto” e “oferta”.
Esse modelo provavelmente deve ser Marketing.
```

Isso não precisa usar IA no começo. Pode ser regra simples por palavras-chave.

---

# Resumo da melhor solução

Construa a interface como um **wizard visual com preview de WhatsApp**, onde o usuário cria:

```txt
Categoria
Nome
Idioma
Cabeçalho
Mensagem principal
Variáveis com exemplos
Rodapé
Botões
```

E o sistema converte internamente para:

```txt
POST /{WABA_ID}/message_templates
```

A camada mais importante é esta:

```txt
Interface amigável
↓
friendly_payload
↓
TemplateValidationService
↓
TemplatePayloadBuilder
↓
meta_payload
↓
MetaApiClient
↓
Graph API oficial
```

Assim o usuário nunca toca em JSON, e seu código fica limpo, centralizado e fácil de manter.

[1]: https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/components/?utm_source=chatgpt.com "Template components - Meta for Developers - Facebook"
[2]: https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/template-management/?utm_source=chatgpt.com "Template management - Meta for Developers - Facebook"
[3]: https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/overview?utm_source=chatgpt.com "Templates - Meta for Developers - Facebook"
[4]: https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/template-categorization?utm_source=chatgpt.com "Template categorization - Meta for Developers - Facebook"
[5]: https://docs.360dialog.com/docs/hub/template-management-ui?utm_source=chatgpt.com "Templates | Client Documentation - Overview - 360Dialog"
[6]: https://docs.clevertap.com/docs/whatsapp-message-templates?utm_source=chatgpt.com "WhatsApp Message Templates"
[7]: https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/marketing-templates/custom-marketing-templates/?utm_source=chatgpt.com "Custom marketing templates - Meta for Developers - Facebook"
[8]: https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/marketing-templates/limited-time-offer-templates/?utm_source=chatgpt.com "Limited-time-offer templates - Meta for Developers - Facebook"
[9]: https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/marketing-templates/coupon-templates/?utm_source=chatgpt.com "Coupon code templates - Meta for Developers - Facebook"
[10]: https://developers.facebook.com/documentation/business-messaging/whatsapp/reference/whatsapp-business-account/template-api?utm_source=chatgpt.com "WhatsApp Cloud API - Template API"
