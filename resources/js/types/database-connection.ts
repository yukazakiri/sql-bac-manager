export interface DatabaseConnection {
    id: number;
    name: string;
    host: string;
    port: string;
    username: string;
    database: string;
    driver: string;
    created_at: string;
    updated_at: string;
}
