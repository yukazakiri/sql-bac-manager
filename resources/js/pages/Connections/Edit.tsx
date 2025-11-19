import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { DatabaseConnection } from '@/types/database-connection';
import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import { FormEventHandler, useState } from 'react';
import { toast } from 'sonner';

interface Props {
    connection: DatabaseConnection;
}

export default function Edit({ connection }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: connection.name,
        host: connection.host,
        port: connection.port,
        username: connection.username,
        password: '', // Don't show password
        database: connection.database,
        driver: connection.driver,
    });

    const [testing, setTesting] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/connections/${connection.id}`);
    };

    const handleTestConnection = async () => {
        setTesting(true);
        try {
            const response = await axios.post('/connections/test', data);
            toast.success(response.data.message);
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Connection failed');
        } finally {
            setTesting(false);
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Connections', href: '/connections' },
                { title: connection.name, href: `/connections/${connection.id}` },
                { title: 'Edit', href: `/connections/${connection.id}/edit` },
            ]}
        >
            <Head title={`Edit ${connection.name}`} />

            <div className="p-6 max-w-2xl mx-auto">
                <Card>
                    <CardHeader>
                        <CardTitle>Edit Connection</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Connection Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="driver">Database Driver</Label>
                                <Select
                                    value={data.driver}
                                    onValueChange={(value) => setData('driver', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select driver" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="mysql">MySQL</SelectItem>
                                        <SelectItem value="pgsql">PostgreSQL</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.driver && <p className="text-sm text-red-500">{errors.driver}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="host">Host</Label>
                                    <Input
                                        id="host"
                                        value={data.host}
                                        onChange={(e) => setData('host', e.target.value)}
                                        required
                                    />
                                    {errors.host && <p className="text-sm text-red-500">{errors.host}</p>}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="port">Port</Label>
                                    <Input
                                        id="port"
                                        value={data.port}
                                        onChange={(e) => setData('port', e.target.value)}
                                        required
                                    />
                                    {errors.port && <p className="text-sm text-red-500">{errors.port}</p>}
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="database">Database Name</Label>
                                <Input
                                    id="database"
                                    value={data.database}
                                    onChange={(e) => setData('database', e.target.value)}
                                    required
                                />
                                {errors.database && <p className="text-sm text-red-500">{errors.database}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="username">Username</Label>
                                <Input
                                    id="username"
                                    value={data.username}
                                    onChange={(e) => setData('username', e.target.value)}
                                    required
                                />
                                {errors.username && <p className="text-sm text-red-500">{errors.username}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="Leave empty to keep current password"
                                />
                                {errors.password && <p className="text-sm text-red-500">{errors.password}</p>}
                            </div>

                            <div className="flex justify-between pt-4">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={handleTestConnection}
                                    disabled={testing}
                                >
                                    {testing ? 'Testing...' : 'Test Connection'}
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    Update Connection
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
