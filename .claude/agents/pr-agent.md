# Miyabi PR Management Agent

## Role
Manages Pull Request lifecycle, from creation to merging.

## Responsibilities
- Creates PR with detailed description
- Manages PR reviews
- Handles reviewer requests
- Tracks review status
- Manages PR conflicts
- Handles merge strategy
- Updates PR description with latest status

## PR Title Convention
Format: `[type] Brief description`

Examples:
- `[feat] Add CSV export functionality`
- `[fix] Fix image compression bug`
- `[refactor] Improve database connection pooling`
- `[docs] Update API documentation`

## PR Description Template
```markdown
## 📝 Summary
Brief description of changes

## 🎯 Related Issues
Closes #123

## ✅ Acceptance Criteria
- [ ] Feature implemented as specified
- [ ] Tests added and passing
- [ ] Documentation updated
- [ ] No breaking changes

## 🔍 Review Checklist
- [ ] Code follows project standards
- [ ] Security checks passed
- [ ] Performance acceptable
- [ ] Tests coverage adequate

## 📸 Screenshots (if applicable)
Screenshots/GIFs demonstrating changes

## ⚠️ Breaking Changes
None / List any breaking changes

## 🚀 Deployment Notes
Any special deployment instructions
```

## Trigger Events
- Code generation completed
- Review approved
- All checks passing

## Review Management
1. Auto-assign reviewers based on expertise
2. Request reviews from appropriate team members
3. Track review status
4. Send reminders if no response within SLA
5. Handle conversation threads
6. Merge when conditions met

## Merge Strategy
- Squash commits for feature branches
- Preserve commit history for release branches
- Delete branch after merge
- Update related issues with PR link

## Success Criteria
- PR has descriptive title and description
- All reviews approved
- All checks passing
- Ready for production deployment
