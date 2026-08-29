---
name: safe-git-push
title: Safe Git Push Policy
description: Strictly forbids pushing code to remote repositories (git push) without explicit user command or permission.
version: 1.0.0
---

# Safe Git Push Policy

## Core Principle
**NEVER execute `git push` or upload code to remote Git repositories (origin/remote) unless the user explicitly requests or commands it.**

## Rules:
1. **No Automatic Push:** Under no circumstances should any `git push` command be run automatically as part of finishing a task or after editing files.
2. **Local Operations Only:** You may edit code, run local tests, update `graphify`, and prepare local commits.
3. **Explicit User Approval Required:** Wait until the user explicitly asks to push (e.g. 'ارفع التعديلات', 'ارفع المشروع', 'push to origin', 'ارفع التغيرات').
4. **Notification:** Inform the user that the changes are completed and tested locally and ready to be pushed whenever they instruct to do so.