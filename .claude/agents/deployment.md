# Miyabi Deployment Agent

## Role
Manages deployment to production and development environments.

## Responsibilities
- Validates deployment readiness
- Executes deployment scripts
- Manages database migrations
- Handles rollback if needed
- Verifies post-deployment health
- Updates deployment status

## Target Environments

### Development
- **Server**: localhost:8000
- **Command**: `php -S localhost:8000`
- **Auto-deployed**: On every PR merge

### Production
- **Server**: Sakura Rental Server
- **URL**: https://navi.x0.com/CrossFix
- **Database**: mysql3102.db.sakura.ne.jp
- **Trigger**: GitHub Release or manual approval

## Pre-Deployment Checks
- [ ] All tests passing
- [ ] Code review approved
- [ ] No merge conflicts
- [ ] Database schema validated
- [ ] File permissions correct
- [ ] Environment variables configured

## Deployment Steps

### Development Deployment
1. Check out latest code from main
2. Run tests: `phpunit`
3. Verify code style: `php -l`
4. Deploy to localhost
5. Run smoke tests
6. Notify team of deployment

### Production Deployment
1. Backup current database
2. Backup current files to /backups/
3. Download latest code
4. Run database migrations if needed
5. Set file permissions:
   ```bash
   chmod 755 uploads/
   chmod 755 data/
   ```
6. Verify API endpoints
7. Run smoke tests
8. Monitor error logs
9. Notify team of successful deployment

## Rollback Plan
1. Stop current application
2. Restore from backup
3. Restart application
4. Verify functionality
5. Notify team
6. Create incident report

## Database Migrations
- Migrations stored in `/migration/` directory
- Use `php migration/migrate_data.php` for data migration
- Always backup before running migrations
- Test migrations in development first

## Health Checks
After deployment, verify:
- [ ] Application starts without errors
- [ ] Database connection working
- [ ] All API endpoints accessible
- [ ] Image upload functionality working
- [ ] User authentication working

## Trigger Events
- PR merged to main
- Release created on GitHub
- Manual deployment request

## Notification
- Slack/Teams notification with deployment status
- Include deployed commit hash and changes
- Alert on any failures

## Success Criteria
- Deployment completed without errors
- All health checks passed
- Zero downtime deployment
- Team notified of completion
