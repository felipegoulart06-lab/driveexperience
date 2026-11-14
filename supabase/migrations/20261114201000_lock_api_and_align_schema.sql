-- Alinha o banco com o esquema que o PHP aplica na instalação
-- e fecha a API pública do Supabase. O painel conecta como postgres,
-- papel que ignora o RLS; anon e authenticated não enxergam nenhuma linha.

ALTER TABLE public.users
    ADD COLUMN IF NOT EXISTS totp_secret TEXT,
    ADD COLUMN IF NOT EXISTS totp_enabled SMALLINT NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS public.cash_sessions (
    id BIGSERIAL PRIMARY KEY,
    status VARCHAR(12) NOT NULL,
    opening_cents INTEGER NOT NULL DEFAULT 0,
    cash_in_cents INTEGER NOT NULL DEFAULT 0,
    cash_out_cents INTEGER NOT NULL DEFAULT 0,
    expected_cents INTEGER,
    counted_cents INTEGER,
    opened_at TIMESTAMP NOT NULL,
    opened_by BIGINT,
    closed_at TIMESTAMP,
    closed_by BIGINT,
    note TEXT
);

REVOKE ALL ON ALL TABLES IN SCHEMA public FROM anon, authenticated;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM anon, authenticated;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM PUBLIC, anon, authenticated;

ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
    REVOKE ALL ON TABLES FROM anon, authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
    REVOKE ALL ON SEQUENCES FROM anon, authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
    REVOKE ALL ON FUNCTIONS FROM anon, authenticated;

DO $$
DECLARE
    item record;
BEGIN
    FOR item IN
        SELECT c.relname AS table_name
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public'
          AND c.relkind = 'r'
    LOOP
        EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', item.table_name);
        EXECUTE format('ALTER TABLE public.%I FORCE ROW LEVEL SECURITY', item.table_name);
        EXECUTE format('DROP POLICY IF EXISTS deny_api ON public.%I', item.table_name);
        EXECUTE format(
            'CREATE POLICY deny_api ON public.%I AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false)',
            item.table_name
        );
    END LOOP;
END $$;

REVOKE ALL ON FUNCTION public.rls_auto_enable() FROM PUBLIC, anon, authenticated;
