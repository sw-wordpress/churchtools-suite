.DEFAULT_GOAL := help

.PHONY: help release
help:
	@echo 'make release VERSION=X.Y.Z[.W]  Update plugin version, commit, tag and push to origin'

release: export CTS_RELEASE_VERSION = $(VERSION)
release:
	bash bin/release.sh
