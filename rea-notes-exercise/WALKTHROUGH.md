# Your first REA exercise

Completed 2026-10-21 (Asia/Singapore) with REA 6.7.0 and reverse-engineer-anything skill version 42.

Official guide: https://rea.tools/first-investigation/
Sample: https://rea.tools/examples/notes-example.zip

## 1. Ask one concrete question

What happens when I click Export notes? Where is notes.csv written and what fields does it contain?

The supplied six-file Electron example is under `notes-example/`. No Electron installation or launch was needed.

## 2. Inspect the artifact

We called `analyze_javascript_application` with the absolute sample folder, `format: directory`, and `detail: summary`. REA parsed all four JavaScript files with zero parse failures. The application inventory was complete; general semantic analysis still reported 71 unknowns. Those unknowns must not be interpreted as proven behavior or dismissed as globally resolved.

The retained parent evidence is:

`ev_666d7f1679d5550d433eead0a8f36901a5701730563ea3852c9e4d82682d3c3c`

## 3. Follow the export path

We called `trace_application_feature` using that retained evidence, an exact channel seed `notes:export`, and direction `incoming`.

REA paired the literal transmission in preload.js with the main-process handler in main.js. Reading the small supplied files confirms the remaining steps:

| File | Lines | What the code does |
| --- | --- | --- |
| renderer.js | 1–3 | Click handler awaits `window.notes.exportCsv()` and displays the returned destination. |
| preload.js | 3–4 | Exposes the API and invokes the `notes:export` channel. |
| main.js | 8–11 | Builds a path under Electron's Downloads directory, formats the notes, writes UTF-8 CSV, and returns the destination. |
| csv.js | 2–7 | Emits `id,title`, quotes titles, doubles internal quotes, and ends rows with newline characters. |

The destination is `path.join(app.getPath("downloads"), "notes.csv")`. The exact Downloads location depends on the machine and Electron runtime. We did not launch Electron or create that export file.

Trace evidence:

`ev_e23ef65535abc192a1a11835ed3c313022ebbd87abb24b0fdfa80936682f4994`

## 4. Predict, then check the actual formatter

Input: a note with id `1` and title `Hello, "REA"`.

Predicted and observed output:

```csv
id,title
1,"Hello, ""REA"""
```

Both outputs ended with a newline and matched byte for byte. This was a direct execution of the supplied csv.js formatter using existing Node.js, not a captured Electron interaction.

To repeat in PowerShell from the notes-example folder:

```powershell
@'
const { toCsv } = require('./csv.js');
process.stdout.write(toCsv([{ id: 1, title: 'Hello, "REA"' }]));
'@ | node
```

## 5. Continue with another focused question

Try: "What happens if the file cannot be written? Follow the error back to the page, and distinguish code evidence from observed execution."

## Saved material and boundaries

- `REA-TOOLS.md`: all 207 tools, grouped by current availability. 77 report available in the current Windows session; native decompilation needs a configured engine.
- `notes-evidence.json`: exported REA evidence, 3 records. The export's zero registered residual-unknown records does not erase the semantic unknowns in the application analysis.
- `notes-example.zip` and `notes-example/`: the official sample and its six original files.

All task files are inside this exercise directory. No additional software was installed, no Codex configuration was changed, and no Astreia files were modified during this REA exercise. No native target was opened, so no native session required closing.
