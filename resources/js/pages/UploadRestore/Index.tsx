import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { DatabaseConnection } from '@/types/database-connection';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Upload, Database } from 'lucide-react';

interface Props {
    connections: DatabaseConnection[];
}

export default function Index({ connections }: Props) {
    const [selectedConnectionId, setSelectedConnectionId] = useState<string>('');
    const [file, setFile] = useState<File | null>(null);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = e.target.files?.[0] || null;
        setFile(selectedFile);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!file) {
            toast.error('Please select a backup file');
            return;
        }

        if (!selectedConnectionId) {
            toast.error('Please select a target connection');
            return;
        }

        const formData = new FormData();
        formData.append('backup_file', file);
        formData.append('target_connection_id', selectedConnectionId);

        fetch('/upload-restore', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.restore_id) {
                toast.success('Restore started successfully!');
                window.location.href = `/upload-restore/${data.restore_id}/output`;
            } else {
                throw new Error(data.message || 'Failed to start restore');
            }
        })
        .catch((error) => {
            toast.error(error.message || 'Failed to start restore');
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Upload & Restore', href: '/upload-restore' },
            ]}
        >
            <Head title="Upload & Restore" />

            <div className="p-6 max-w-6xl mx-auto space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Upload className="h-5 w-5" />
                            Upload Backup & Restore
                        </CardTitle>
                        <CardDescription>
                            Upload a backup file and restore it to a database connection
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Backup File</label>
                                <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors">
                                    <input
                                        type="file"
                                        onChange={handleFileChange}
                                        accept=".sql,.dump,.bz2,.gz"
                                        className="hidden"
                                        id="file-upload"
                                    />
                                    <label htmlFor="file-upload" className="cursor-pointer">
                                        <Upload className="h-12 w-12 mx-auto text-gray-400 mb-2" />
                                        {file ? (
                                            <p className="text-sm font-medium">{file.name}</p>
                                        ) : (
                                            <p className="text-sm text-gray-600">
                                                Click to upload a backup file
                                            </p>
                                        )}
                                        <p className="text-xs text-gray-500 mt-1">
                                            Supports: .sql, .dump, .bz2, .gz
                                        </p>
                                    </label>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">Target Database Connection</label>
                                <Select value={selectedConnectionId} onValueChange={setSelectedConnectionId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a database connection" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {connections.map((conn) => (
                                            <SelectItem key={conn.id} value={conn.id.toString()}>
                                                <div className="flex items-center gap-2">
                                                    <Database className="h-4 w-4" />
                                                    <span>{conn.name}</span>
                                                    <span className="text-sm text-muted-foreground">
                                                        ({conn.username}@{conn.host}:{conn.port}/{conn.database})
                                                    </span>
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button type="submit" size="lg" className="w-full">
                                Start Restore
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
