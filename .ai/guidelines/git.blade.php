# Git and pull requests

- Never add AI attribution to commit messages, PR titles, or PR descriptions. This includes
  `Co-Authored-By: Claude`, any `noreply@anthropic.com` address, "Generated with Claude Code",
  `Claude-Session:` trailers, `claude.ai/code/session_` links, and robot emoji.
- Commit messages are a **subject line only**, at most 60 characters: a plain type prefix with no
  scope parentheses, then a short summary — `feat: quick add transactions`. No body, no
  requirement-ID refs, no trailers. Requirement IDs still belong in code PHPDoc, not in git.
- PR titles and descriptions follow the same restraint: a plain summary, no requirement IDs.
