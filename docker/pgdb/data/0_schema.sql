CREATE TABLE public.users (
    id serial NOT NULL,
    ref_id int NOT NULL,
    ref_username text NOT NULL,
    email text NOT NULL,
    profile_image_url text NOT NULL DEFAULT ''
);

ALTER TABLE public.users ADD ref_access_token text NOT NULL DEFAULT '';
ALTER TABLE public.users ADD ref_refresh_token text NOT NULL DEFAULT '';
ALTER TABLE public.users ADD CONSTRAINT users_pk PRIMARY KEY (id);
CREATE INDEX users_ref_id_idx ON public.users (ref_id);
