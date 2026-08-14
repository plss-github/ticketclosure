# Ticket Closure — Auto-approval Plugin for GLPI

The **Ticket Closure** plugin lets you configure specific ITIL categories for which, as soon as a solution is added to a ticket, GLPI skips the requester's approval step entirely and closes the ticket automatically.

---

## Features

- **Category-based auto-approval** — pick one or more ITIL categories in a single global list; any ticket in a matching category is closed the moment a solution is submitted
- **Core-equivalent behaviour** — the plugin reproduces the exact same update GLPI performs when a requester manually approves a solution (`status = Closed`, `closedate` set, solution row marked `Accepted` with `users_id_approval`/`date_approval` filled in), so closed tickets are indistinguishable from a manually approved one in history, notifications and statistics
- **Non-invasive** — categories *not* on the list keep GLPI's normal behaviour (ticket goes to *Solved*, waiting for the requester to approve or refuse), including the native per-entity `autoclose_delay` setting
- **Single settings screen** — a "Ticket Closure" tab under **Setup → General** with a multi-select of ITIL categories

---

## Requirements

| Dependency | Version |
|---|---|
| GLPI | 10.0.0 – 11.x |
| PHP | ≥ 7.4 |

---

## Installation

1. Download the plugin archive and extract it to `<glpi>/marketplace/ticketclosure/`
2. In GLPI go to **Setup → Plugins**, find *Ticket Closure* and click **Install**, then **Enable**
3. Configure the categories under **Setup → General → Ticket Closure**

---

## Configuration

Go to **Setup → General**, open the **Ticket Closure** tab, and select the ITIL categories that should skip approval. Save.

From that point on, adding a solution to a ticket in one of those categories closes it immediately — no action needed from the requester.

---

## How it works

```
Solution added to a ticket
        │
        └─ Ticket's ITIL category is in the configured list?
               │ No                          │ Yes
               ▼                             ▼
     GLPI's normal flow applies       Ticket forced to Closed
     (Solved, waiting for the        (same update as a manual
      requester's approval,           approval: closedate set,
      or per-entity autoclose_delay)  solution marked Accepted)
```

The plugin hooks into `item_add` for `ITILSolution`, which fires right after GLPI's own solution-handling logic has already run — the plugin only overrides the outcome for the configured categories, leaving everything else untouched.

---

## Troubleshooting

The plugin runs inside a hook, with no screen of its own, so it writes one line per
solution to `<GLPI_LOG_DIR>/ticketclosure.log` (usually `files/_log/ticketclosure.log`,
or `/var/glpi/logs/` on the official Docker image) saying what it decided:

```
chamado 4: fechado automaticamente (categoria 3)
chamado 3: categoria 12 fora da lista [3, 7]
chamado 5: FALHOU ao fechar -- status continuou 5, esperado 6 -- mensagens na fila: ...
```

The `FALHOU` line is the interesting one. Closing a ticket goes through GLPI's whole
update pipeline — ticket template, business rules, and the `pre_item_update` hook of
every active plugin — and any of them may drop `status` from the input while the
update still reports success. Behaviours (`is_ticketrealtime_mandatory`,
`is_ticketsolutiontype_mandatory`, `is_tickettech_mandatory`, `is_tickettasktodo`, …)
does exactly that. The queued messages appended to the log line name the culprit.

To inspect a ticket that should have been closed, open the diagnostic screen — the
button sits right under the category list in **Setup → General → Ticket Closure**, or
go straight to `<glpi>/plugins/ticketclosure/front/diagnose.php`. It needs no server
access, only the `config` update right.

It reports whether the plugin is active and hooked, the raw stored configuration, the
ticket's category and whether it matches, the solution's approval status, which other
plugins listen to `pre_item_update` on `Ticket`, and the plugin's last decisions.
**Try to close now** runs the hook's exact update on that ticket and shows what the
pipeline answered.

The solution's approval status tells the two failure modes apart:

| Solution shows | Meaning |
|---|---|
| *Accepted* | the plugin ran; only `status` was dropped → something in the update pipeline |
| *Waiting for approval* | the plugin never acted on this ticket → configuration or category |

The same diagnostic is available from the command line when there is shell access:

```bash
cd <glpi>                                                      # the GLPI root
php plugins/ticketclosure/tools/diagnose.php 2083969           # read-only
php plugins/ticketclosure/tools/diagnose.php 2083969 --fechar  # actually try to close it
```

Add `--usuario-id=N` to run as the user who submitted the solution instead of the
super-admin, which reproduces any rights-related restriction.

---

## Author

- Ampris

## License

GPL v2+
