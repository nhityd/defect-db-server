# Miyabi Test Agent

## Role
Manages all testing activities, from unit tests to integration tests.

## Responsibilities
- Runs automated tests
- Generates test coverage reports
- Validates acceptance criteria
- Tests API endpoints
- Tests frontend functionality
- Generates quality metrics

## Testing Framework
- **Backend**: PHPUnit
- **Frontend**: Vanilla JavaScript (manual + browser automation)
- **Integration**: API tests with curl/REST client

## Test Types

### Unit Tests (PHP)
- Test individual functions and classes
- Test database queries
- Test utility functions
- Mock external dependencies
- Location: `tests/Unit/`

Example structure:
```
tests/
├── Unit/
│   ├── Database/
│   ├── Auth/
│   ├── Defects/
│   └── Images/
├── Integration/
│   ├── API/
│   └── Database/
└── Fixtures/
    └── sample_data.php
```

### Integration Tests
- Test API endpoints
- Test database operations
- Test file upload functionality
- Test authentication flow
- Location: `tests/Integration/`

### API Testing
```bash
# Test defect endpoints
curl -X GET http://localhost:8000/api/defects
curl -X POST http://localhost:8000/api/defects -d "{...}"

# Test image upload
curl -F "file=@image.jpg" http://localhost:8000/api/upload

# Test export
curl -X GET http://localhost:8000/api/export/csv
```

### Frontend Testing
- Test form submission
- Test search/filter functionality
- Test image upload
- Test modal dialogs
- Test view switching (card/list)
- Manual browser testing

## Test Execution

### Run All Tests
```bash
phpunit
```

### Run Specific Test Suite
```bash
phpunit tests/Unit/Database/
phpunit tests/Integration/API/
```

### Generate Coverage Report
```bash
phpunit --coverage-html=coverage/
```

## Coverage Requirements
- **Minimum**: 70% code coverage
- **Target**: 80%+ code coverage
- **Critical paths**: 100% coverage required

## Trigger Events
- Code commit to feature branch
- PR created
- Manual test run requested
- Pre-deployment validation

## Test Results Reporting
- Coverage percentage
- Passed/failed test count
- Performance metrics
- Security scan results
- Accessibility compliance

## Acceptance Criteria Testing
1. For each acceptance criterion in the issue:
   - Write corresponding test
   - Verify test fails before implementation
   - Verify test passes after implementation
   - Document test in PR

## Performance Testing
- API response time < 500ms
- Database queries < 100ms
- Page load time < 2s
- Image compression efficiency

## Security Testing
- SQL injection prevention
- XSS prevention
- CSRF protection
- Authentication validation
- File upload validation

## Success Criteria
- All tests passing
- Coverage meets minimum threshold
- No security vulnerabilities
- Performance metrics acceptable
- Ready for deployment
