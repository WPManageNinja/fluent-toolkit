# Fluent MCP standard v1

Rules for every Fluent MCP server: each product's own server (FluentCart, FluentCRM,
Fluent Support, and the next ones) and the FluentHub router. An AI agent must see one
behaviour on all of them.

The readers of an MCP server are AI agents. Each rule exists because an agent got blocked
or paid tokens for nothing without it.

## 1. Errors use one envelope

Every error a tool returns is this JSON, as the error text:

```json
{"error": {"code": "invalid_param", "message": "status is not one of open, closed.", "retryable": false, "fields": ["status"]}}
```

- `message` says what to change and names the allowed values.
- `fields` lists the parameters at fault, beside `code`.
- `retryable` is true only when the same call can succeed later.
- The MCP adapter sends only the message of a `WP_Error` to the client. So the envelope is
  the message.
- WordPress checks the input against the schema before the tool runs and answers with a
  plain sentence. Hook `wp_ability_validate_input` to return the envelope for these too.
- An error must not carry SQL, table names, file paths or the database name.

## 2. Two codes for input problems

- `invalid_param`: any wrong, missing or unreadable value. This covers a schema failure, a
  bad enum value, a missing required value, an unreadable date and a reversed range.
- `unknown_param`: a parameter the tool does not have. Add `allowed`, the list of valid
  parameter names.

Other codes are for domain cases only, such as `not_found` and `forbidden`.

Refuse an unknown parameter. A misspelled filter that is ignored returns the unfiltered
result as if it were the answer.

## 3. Warnings go in `meta.warnings`

`meta.warnings` is an array of strings. Use it for anything the tool changed or left out,
for example a `per_page` that was lowered to the maximum.

## 4. Dates

- Out: ISO 8601 in the site timezone, with the offset.
- In: a value without an offset is read in the site timezone. A value with an offset or `Z`
  is honoured.
- Accept only a written date or date and time. PHP's date parser reads words: "Tuesday
  10:00" becomes today at 10:00, and "x" is a timezone. Refuse them with `invalid_param`.
- Refuse a reversed range with `invalid_param`.

## 5. Money

A decimal number in major units, beside its currency code. Not cents, and not a formatted
string.

## 6. Text for AI is plain English

Text an AI reads has no translation function: labels, descriptions, schema text, server
instructions, prompts, summaries, warnings and errors. A translated tool description makes
the tool worse on a non-English site.

Text a person sees in WordPress keeps its translation call: admin notices, emails, and
text stored on a record that staff or customers read.

## 7. Each server works alone

A site admin can use a product's own endpoint without the FluentHub router, and without
the FluentHub plugin when the standalone MCP Adapter plugin is active.

- No product text mentions the hub, its `help` action or its router tool names.
- The rules a model must not miss are also in the context tool's result. A direct client
  gets the server instructions once, in `initialize`, and some clients do not show them
  to the model.
- Every tool is callable from `tools/list` alone. Each parameter has a description, an
  enum, or a name that needs neither.
- The first sentence of each tool description is complete within 160 characters. The hub
  shows that sentence as the one-line description of the action.

## 8. A tool result goes out once

The MCP adapter puts every result in the response twice: as JSON text in `content` and
again as `structuredContent`. The two are identical. What reaches the model depends on
the client:

| Client | Both copies sent | Text only |
|---|---|---|
| Claude Code 2.1.325 | The model gets the structured copy | The model gets the text |
| Codex CLI 0.159.2 | The model gets both | The model gets the text |
| Cursor 3.14.7 (forum report) | The model gets the text | The model gets the text |

So a Codex user pays for every result twice. Send the text block only:

- Hook `mcp_adapter_tool_call_result`. When the tool belongs to your own server, add a
  `rest_post_dispatch` callback once. A REST request that ran none of your tools pays
  nothing.
- In that callback, check that the request is on your own MCP route. The hub runs a
  product tool and then applies `mcp_adapter_tool_call_result` with the product's server,
  so the first check also passes on a hub call.
- Remove `structuredContent` only when the first content block is non-empty text. Decode
  the response as objects, so an empty JSON object stays `{}`. Handle a batch. Leave
  errors and other methods alone.
- Give the site a filter to turn it off, for a program that reads `structuredContent`.

Reference: `HubServer::armSingleResultCopy()` and `HubServer::sendResultOnce()` in
`includes/Mcp/HubServer.php`.

## 9. No output schema by default

A tool declares no `outputSchema`, unless a named client needs typed results for that
tool.

- A model reads the result itself. The results describe themselves.
- Every output schema is sent to a direct client in `tools/list` before the first task.
  FluentCart's seven cost about 2,960 tokens.
- The MCP specification requires `structuredContent` when a tool declares an output
  schema. That tool must then send both copies again (rule 8).
- A schema that falls behind the result lets a strict client reject a valid result.

Add one for a single tool when a real client needs it, for example an SDK integration or
an automation platform, and accept the second copy for that tool.

## Known differences, November 2026

- FluentCart returns dates in UTC, by decision, and says so in its instructions and in
  `get-store-context`. Report windows are read in the store timezone. It is the one known
  difference on rule 4.
- FluentCart's advanced search keeps its own codes for a bad filter: `invalid_operator`,
  `invalid_structure`, `invalid_value`, `missing_value`, `unknown_property`,
  `unknown_entity`, `too_many_conditions`, `empty_filters` and `no_advanced_options`. Its
  other input codes follow rule 2.
- FluentBooking's `get-available-slots` returns times of day (`HH:MM`) keyed by date, in the
  timezone named in `meta.timezone`. An offset on every slot would triple the result.
- FluentCRM returns a flat result (`items`, `total`, ...) and nests extra error data under
  `details`, beside `fields`. FluentCart and Fluent Support return `summary`, `data` and
  `meta`.
