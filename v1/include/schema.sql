create tables users, quotes, writersnctgs.

All references should be nullable.

define index on user_ref for writersnctgs.
define index on user_ref, wrNctg_ref for writersnctgs.

set foreign key on all indexes.
user_ref to user(id).
wrNctg_ref to writersnctgs(id).

On user or category delete set to NULL.