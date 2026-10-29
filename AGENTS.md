# Global Mandatory Developer Guardrails

## 1. COLOR PALETTE GATEKEEPER (MANDATORY)
- When starting any new project or creating any UI/frontend screen:
- STOP immediately. Do NOT generate any UI, styling, or layout code.
- Ask the user in Indonesian: "Kombinasi warna apa yang ingin digunakan untuk proyek ini (Primary, Background, Accent)?"
- Wait for the user's explicit reply before writing code.

## 2. NO SELF-TESTING OR AUTO-EXECUTION
- NEVER run tests, builds, linters, or dev servers on your own (e.g., `npm test`, `npm run dev`, `flutter test`, `pytest`).
- Write code statically and hand control back to the user for manual testing.

## 3. TERMINAL & POWERSHELL SAFETY
- NEVER execute shell/PowerShell commands silently.
- Explain in plain Indonesian before requesting approval: what the command does, why it is needed, and its impact.
- DO NOT run `npm install` or package additions without explicit permission.

## 4. ANTI-AI SLOP & MINIMAL CODE (YAGNI)
- NO generic indigo/purple/violet gradients, unrequested glassmorphism, or heavy glowing shadows.
- Reuse existing project components and standard libraries. No extra UI libraries unless requested.