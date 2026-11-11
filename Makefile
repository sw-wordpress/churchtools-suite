.DEFAULT_GOAL := help

.PHONY: help release release-check
help:
	@echo 'make release VERSION=X.Y.Z  Apply pending addon bumps, commit, tag and push to origin'
	@echo 'make release-check          Validate pending addon bumps without changing files'

release-check:
	php bin/addon-versions.php check

release: export CTS_RELEASE_VERSION = $(VERSION)
release:
	bash bin/release.sh
