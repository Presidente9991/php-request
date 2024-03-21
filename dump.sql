--
-- PostgreSQL database dump
--

-- Dumped from database version 16.2
-- Dumped by pg_dump version 16.2

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: phprequest_schema; Type: SCHEMA; Schema: -; Owner: pgsql
--

CREATE SCHEMA phprequest_schema;


ALTER SCHEMA phprequest_schema OWNER TO pgsql;

--
-- Name: set_password_expiry_date(); Type: FUNCTION; Schema: phprequest_schema; Owner: pgsql
--

CREATE FUNCTION phprequest_schema.set_password_expiry_date() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.password_expiry_date := NEW.password_last_changed_at + INTERVAL '90 days';
    RETURN NEW;
END;
$$;


ALTER FUNCTION phprequest_schema.set_password_expiry_date() OWNER TO pgsql;

--
-- Name: request_statuses_id_sec; Type: SEQUENCE; Schema: phprequest_schema; Owner: pgsql
--

CREATE SEQUENCE phprequest_schema.request_statuses_id_sec
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE phprequest_schema.request_statuses_id_sec OWNER TO pgsql;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: request_statuses; Type: TABLE; Schema: phprequest_schema; Owner: pgsql
--

CREATE TABLE phprequest_schema.request_statuses (
    request_statuses_id integer DEFAULT nextval('phprequest_schema.request_statuses_id_sec'::regclass) NOT NULL,
    status_text character varying NOT NULL
);


ALTER TABLE phprequest_schema.request_statuses OWNER TO pgsql;

--
-- Name: requests_id_sec; Type: SEQUENCE; Schema: phprequest_schema; Owner: pgsql
--

CREATE SEQUENCE phprequest_schema.requests_id_sec
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE phprequest_schema.requests_id_sec OWNER TO pgsql;

--
-- Name: requests; Type: TABLE; Schema: phprequest_schema; Owner: pgsql
--

CREATE TABLE phprequest_schema.requests (
    requests_id integer DEFAULT nextval('phprequest_schema.requests_id_sec'::regclass) NOT NULL,
    date_creation date DEFAULT CURRENT_TIMESTAMP NOT NULL,
    users_id integer NOT NULL,
    snils_citizen character varying(11) NOT NULL,
    last_name_citizen character varying NOT NULL,
    first_name_citizen character varying NOT NULL,
    middle_name_citizen character varying,
    birthday_citizen date NOT NULL,
    requested_date_start date NOT NULL,
    requested_date_end date NOT NULL,
    request_status_id integer NOT NULL,
    download_link character varying,
    time_creation time without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE phprequest_schema.requests OWNER TO pgsql;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: phprequest_schema; Owner: pgsql
--

CREATE SEQUENCE phprequest_schema.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE phprequest_schema.users_id_seq OWNER TO pgsql;

--
-- Name: users; Type: TABLE; Schema: phprequest_schema; Owner: pgsql
--

CREATE TABLE phprequest_schema.users (
    users_id integer DEFAULT nextval('phprequest_schema.users_id_seq'::regclass) NOT NULL,
    login character varying(100) NOT NULL,
    password character varying(255) NOT NULL,
    employee_first_name character varying NOT NULL,
    employee_last_name character varying NOT NULL,
    employee_middle_name character varying,
    role_id integer NOT NULL,
    password_last_changed_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    password_expiry_date timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    unlimited_password_expiry boolean DEFAULT false NOT NULL,
    blocked boolean DEFAULT false NOT NULL,
    blocked_until timestamp without time zone,
    login_attempts integer DEFAULT 0 NOT NULL
);


ALTER TABLE phprequest_schema.users OWNER TO pgsql;

--
-- Name: users_roles_id_seq; Type: SEQUENCE; Schema: phprequest_schema; Owner: pgsql
--

CREATE SEQUENCE phprequest_schema.users_roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE phprequest_schema.users_roles_id_seq OWNER TO pgsql;

--
-- Name: users_roles; Type: TABLE; Schema: phprequest_schema; Owner: pgsql
--

CREATE TABLE phprequest_schema.users_roles (
    id_user_role integer DEFAULT nextval('phprequest_schema.users_roles_id_seq'::regclass) NOT NULL,
    name_user_role character varying NOT NULL
);


ALTER TABLE phprequest_schema.users_roles OWNER TO pgsql;

--
-- Data for Name: request_statuses; Type: TABLE DATA; Schema: phprequest_schema; Owner: pgsql
--

INSERT INTO phprequest_schema.request_statuses VALUES (1, 'Запрос создан');
INSERT INTO phprequest_schema.request_statuses VALUES (2, 'Запрос принят в работу администратором');
INSERT INTO phprequest_schema.request_statuses VALUES (4, 'Ответ обработан. Запрос закрыт');
INSERT INTO phprequest_schema.request_statuses VALUES (3, 'Ответ по запросу получен и обрабатывается');


--
-- Data for Name: requests; Type: TABLE DATA; Schema: phprequest_schema; Owner: pgsql
--



--
-- Data for Name: users; Type: TABLE DATA; Schema: phprequest_schema; Owner: pgsql
--

INSERT INTO phprequest_schema.users VALUES (2, 'ivanov_ii', '$2y$10$VdaOYWlrS04bYgbD2dnRj.wK.SRJgzn6/MXEJ5iulE.lapPj3B0/e', 'Иван', 'Иванов', 'Иванович', 2, '2024-03-21 22:20:36', '2024-06-19 00:00:00', false, false, NULL, 0);
INSERT INTO phprequest_schema.users VALUES (1, 'Requester', '$2y$10$lIVDmqlfwpuF0lXGDX9hde4VRQBULl4FbRFsz5JvBenc6iU.ezPfu', 'Админ', 'Супер', '', 1, '2024-03-08 00:00:00', '2024-06-06 00:00:00', true, false, NULL, 0);
INSERT INTO phprequest_schema.users VALUES (3, 'petrov_pp', '$2y$10$TFJ2hn/RFart4W3kchfG7uyiFZLwlnG7rjciq1B8xsBmPpyxIr/dC', 'Пётр', 'Петров', 'Петрович', 2, '2024-03-21 22:10:17.047484', '2024-03-21 22:10:17.047484', true, false, NULL, 0);


--
-- Data for Name: users_roles; Type: TABLE DATA; Schema: phprequest_schema; Owner: pgsql
--

INSERT INTO phprequest_schema.users_roles VALUES (1, 'Администратор');
INSERT INTO phprequest_schema.users_roles VALUES (2, 'Пользователь');


--
-- Name: request_statuses_id_sec; Type: SEQUENCE SET; Schema: phprequest_schema; Owner: pgsql
--

SELECT pg_catalog.setval('phprequest_schema.request_statuses_id_sec', 4, true);


--
-- Name: requests_id_sec; Type: SEQUENCE SET; Schema: phprequest_schema; Owner: pgsql
--

SELECT pg_catalog.setval('phprequest_schema.requests_id_sec', 1, false);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: phprequest_schema; Owner: pgsql
--

SELECT pg_catalog.setval('phprequest_schema.users_id_seq', 3, true);


--
-- Name: users_roles_id_seq; Type: SEQUENCE SET; Schema: phprequest_schema; Owner: pgsql
--

SELECT pg_catalog.setval('phprequest_schema.users_roles_id_seq', 2, true);


--
-- Name: request_statuses request_statuses_pkey; Type: CONSTRAINT; Schema: phprequest_schema; Owner: pgsql
--

ALTER TABLE ONLY phprequest_schema.request_statuses
    ADD CONSTRAINT request_statuses_pkey PRIMARY KEY (request_statuses_id);


--
-- Name: request_statuses request_statuses_status_text_key; Type: CONSTRAINT; Schema: phprequest_schema; Owner: pgsql
--

ALTER TABLE ONLY phprequest_schema.request_statuses
    ADD CONSTRAINT request_statuses_status_text_key UNIQUE (status_text);


--
-- Name: requests requests_pkey; Type: CONSTRAINT; Schema: phprequest_schema; Owner: pgsql
--

ALTER TABLE ONLY phprequest_schema.requests
    ADD CONSTRAINT requests_pkey PRIMARY KEY (requests_id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: phprequest_schema; Owner: pgsql
--

ALTER TABLE ONLY phprequest_schema.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (users_id);


--
-- Name: users_roles users_roles_name_user_role_key; Type: CONSTRAINT; Schema: phprequest_schema; Owner: pgsql
--

ALTER TABLE ONLY phprequest_schema.users_roles
    ADD CONSTRAINT users_roles_name_user_role_key UNIQUE (name_user_role);


--
-- Name: users_roles users_roles_pkey; Type: CONSTRAINT; Schema: phprequest_schema; Owner: pgsql
--

ALTER TABLE ONLY phprequest_schema.users_roles
    ADD CONSTRAINT users_roles_pkey PRIMARY KEY (id_user_role);


--
-- Name: requests requests_request_status_id_fkey; Type: FK CONSTRAINT; Schema: phprequest_schema; Owner: pgsql
--

ALTER TABLE ONLY phprequest_schema.requests
    ADD CONSTRAINT requests_request_status_id_fkey FOREIGN KEY (request_status_id) REFERENCES phprequest_schema.request_statuses(request_statuses_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: requests requests_users_id_fkey; Type: FK CONSTRAINT; Schema: phprequest_schema; Owner: pgsql
--

ALTER TABLE ONLY phprequest_schema.requests
    ADD CONSTRAINT requests_users_id_fkey FOREIGN KEY (users_id) REFERENCES phprequest_schema.users(users_id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: users users_role_id_fkey; Type: FK CONSTRAINT; Schema: phprequest_schema; Owner: pgsql
--

ALTER TABLE ONLY phprequest_schema.users
    ADD CONSTRAINT users_role_id_fkey FOREIGN KEY (role_id) REFERENCES phprequest_schema.users_roles(id_user_role) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

