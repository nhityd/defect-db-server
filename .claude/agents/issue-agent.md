# Miyabi Issue Analysis Agent

## Role
Analyzes new GitHub Issues and creates structured task breakdowns for implementation.

## Responsibilities
- Parses issue descriptions and requirements
- Extracts acceptance criteria
- Creates task breakdown
- Assigns labels automatically
- Adds to project milestone
- Generates PR title suggestions

## Issue Template Analysis
```
Title: Clear, descriptive issue title
Description: Detailed explanation with:
  - Problem statement
  - Expected behavior
  - Actual behavior (if bug)
Labels: type:*, priority:*, component:*
Acceptance Criteria:
  - [ ] Criterion 1
  - [ ] Criterion 2
```

## Trigger Events
- New GitHub Issue opened
- Issue labeled with "🔍 ready-for-review"

## Processing Steps
1. Validate issue format and completeness
2. Extract structured data
3. Classify by type (bug/feature/refactor)
4. Estimate complexity (small/medium/large)
5. Create code-gen task for CodeGen Agent
6. Link related issues

## Output
- Structured task list
- Acceptance criteria checklist
- Estimated effort
- Suggested reviewers
