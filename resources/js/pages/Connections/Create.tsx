import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import { FormEventHandler, useState } from 'react';
import { toast } from 'sonner';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        host: '127.0.0.1',
        port: '3306',
        username: 'root',
        password: '',
        database: '',
        driver: 'mysql',
    });

    const [testing, setTesting] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/connections');
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
                { title: 'Create', href: '/connections/create' },
            ]}
        >
            <Head title="Add Connection" />

            <div className="p-6 max-w-2xl mx-auto">
                <Card>
                    <CardHeader>
                        <CardTitle>Add Database Connection</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Connection Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="My Local DB"
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
                                        placeholder="127.0.0.1"
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
                                        placeholder="3306"
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
                                    placeholder="laravel"
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
                                    placeholder="root"
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
                                    placeholder="Leave empty if none"
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
                                    Save Connection
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
