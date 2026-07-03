#!/usr/bin/env bash

GIT_DIR="$(git rev-parse --git-dir)"
REBASE_DIRS=(
    "rebase-merge"
    "rebase-apply"
)

################################################################################

# run git-hooks on interactive rebase (this only applies to "edit" commits)
test -f "${GIT_DIR}/rebase-merge/interactive" \
    && exit 0

########################################

#
for dir in "${REBASE_DIRS[@]}"
do
    test -d "${GIT_DIR}/${dir}" \
        && exit 1
done

################################################################################

# exit (0 = success) if we were able to fall through
exit 0
