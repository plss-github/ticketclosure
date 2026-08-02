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

## Author

- Ampris

## License

GPL v2+
