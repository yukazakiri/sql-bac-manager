import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { DatabaseConnection } from '@/types/database-connection';
import { Head, Link, router } from '@inertiajs/react';
import { Database, Edit, Plus, Trash2 } from 'lucide-react';

interface Props {
    connections: DatabaseConnection[];
}

export default function Index({ connections }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this connection?')) {
            router.delete(`/connections/${id}`);
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Connections', href: '/connections' },
            ]}
        >
            <Head title="Database Connections" />

            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold">Database Connections</h1>
                    <Link href="/connections/create">
                        <Button>
                            <Plus className="w-4 h-4 mr-2" />
                            Add Connection
                        </Button>
                    </Link>
                </div>

                {connections.length === 0 ? (
                    <div className="text-center py-12 border-2 border-dashed rounded-lg">
                        <Database className="w-12 h-12 mx-auto text-gray-400 mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">No connections found</h3>
                        <p className="text-gray-500 dark:text-gray-400 mb-4">Get started by adding your first database connection.</p>
                        <Link href="/connections/create">
                            <Button variant="outline">Add Connection</Button>
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {connections.map((connection) => (
                            <Card key={connection.id}>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Database className="w-5 h-5" />
                                        {connection.name}
                                    </CardTitle>
                                    <CardDescription>
                                        {connection.username}@{connection.host}:{connection.port}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-gray-500">Database: {connection.database}</p>
                                    <p className="text-sm text-gray-500">Driver: {connection.driver}</p>
                                </CardContent>
                                <CardFooter className="flex justify-between">
                                    <Link href={`/connections/${connection.id}`}>
                                        <Button variant="default">Manage</Button>
                                    </Link>
                                    <div className="flex gap-2">
                                        <Link href={`/connections/${connection.id}/edit`}>
                                            <Button variant="outline" size="icon">
                                                <Edit className="w-4 h-4" />
                                            </Button>
                                        </Link>
                                        <Button
                                            variant="destructive"
                                            size="icon"
                                            onClick={() => handleDelete(connection.id)}
                                        >
                                            <Trash2 className="w-4 h-4" />
                                        </Button>
                                    </div>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
