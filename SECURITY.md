# Security Policy

## Supported Versions

We release patches for security vulnerabilities. Which versions are eligible for receiving such patches depends on the CVSS v3.0 Rating:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within Filexus, please send an email to John Michael Manlupig at manlupigjohnmichael@gmail.com. All security vulnerabilities will be promptly addressed.

**Please do not publicly disclose the issue until it has been addressed by our team.**

### What to Include

When reporting a security issue, please include:

- A description of the vulnerability
- Steps to reproduce the issue
- Possible impacts of the vulnerability
- Any potential solutions you've identified

## Security Update Process

1. The security report is received and assigned to a primary handler
2. The problem is confirmed and a list of affected versions is determined
3. Code is audited to find any similar problems
4. Fixes are prepared for all supported releases
5. New versions are released and announcements are made

## Disclosure Policy

We follow a coordinated disclosure process:

1. Security issue is reported privately
2. We confirm the issue and determine affected versions
3. We prepare fixes and release new versions
4. We publicly disclose the vulnerability details after fixes are available

## Security Best Practices

When using Filexus, we recommend:

1. **Validate File Uploads**: Always validate file types, sizes, and content before accepting uploads
2. **Use Allowed MIME Types**: Configure `allowed_mimes` in collection settings to restrict file types
3. **Set File Size Limits**: Use `max_file_size` to prevent large file attacks
4. **Sanitize File Names**: Filexus generates UUID-based names, but original names should be sanitized before display
5. **Use Private Disks**: For sensitive files, use private storage disks and generate temporary URLs
6. **Enable Pruning**: Schedule the `filexus:prune` command to clean up expired and orphaned files regularly
7. **Monitor File Hashes**: Use the hash field for integrity verification and duplicate detection

## Known Security Considerations

### File Type Validation

While Filexus validates MIME types, be aware that MIME types can be spoofed. For critical applications:

- Perform additional server-side content validation
- Use virus scanning for uploaded files
- Implement additional file type verification beyond MIME types

### Storage Security

- Configure Laravel's filesystem disks appropriately
- Use private disks for sensitive data
- Implement proper access controls on storage directories
- Consider encryption for sensitive files at rest

### Access Control

- Implement authorization checks before allowing file access
- Use Laravel's policies to control who can attach/detach files
- Generate temporary signed URLs for file downloads in private storage

## Credits

We would like to thank all security researchers who responsibly disclose vulnerabilities to us.
