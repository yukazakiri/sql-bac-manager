import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { DatabaseConnection } from '@/types/database-connection';
import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, Database, FileUp, Loader2, Upload } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface Props {
    connections: DatabaseConnection[];
}

export default function Index({ connections }: Props) {
    const [selectedConnectionId, setSelectedConnectionId] =
        useState<string>('');
    const [file, setFile] = useState<File | null>(null);

    const { data, setData, post, processing, errors, progress } = useForm({
        backup_file: null as File | null,
        target_connection_id: '',
    });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = e.target.files?.[0] || null;
        setFile(selectedFile);
        if (selectedFile) {
            setData('backup_file', selectedFile);
        } else {
            setData('backup_file', null);
        }
    };

    const handleConnectionChange = (value: string) => {
        setSelectedConnectionId(value);
        setData('target_connection_id', value);
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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

        post('/upload-restore', {
            forceFormData: true,
            onError: (errors) => {
                const errorMessages = Object.values(errors).flat().join(', ');
                toast.error(errorMessages || 'Failed to start restore');
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Upload & Restore', href: '/upload-restore' },
            ]}
        >
            <Head title="Upload & Restore" />

            <div className="mx-auto max-w-4xl space-y-6 p-6">
                <div className="grid gap-6">
                    <Card className="border-l-4 border-l-blue-500 shadow-md">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-xl">
                                <Upload className="h-6 w-6 text-blue-500" />
                                Restore from Backup File
                            </CardTitle>
                            <CardDescription className="text-base">
                                Upload a local SQL backup file and restore it
                                directly to one of your configured database
                                connections.
                            </CardDescription>
                        </CardHeader>
                    </Card>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <Card>
                            <CardContent className="pt-6">
                                <div className="grid gap-8 md:grid-cols-2">
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-2 font-semibold text-gray-700">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                                1
                                            </div>
                                            Select Backup File
                                        </div>

                                        <div className="group relative">
                                            <input
                                                type="file"
                                                onChange={handleFileChange}
                                                accept=".sql,.dump,.bz2,.gz"
                                                className="hidden"
                                                id="file-upload"
                                                disabled={processing}
                                            />
                                            <label
                                                htmlFor="file-upload"
                                                className={`flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 text-center transition-all duration-200 ${
                                                    file
                                                        ? 'border-blue-400 bg-blue-50/50'
                                                        : 'border-gray-200 hover:border-gray-400 hover:bg-gray-50'
                                                } ${processing ? 'cursor-not-allowed opacity-50' : ''}`}
                                            >
                                                {file ? (
                                                    <div className="space-y-2">
                                                        <CheckCircle2 className="mx-auto h-12 w-12 text-green-500" />
                                                        <div className="text-sm font-semibold text-gray-900">
                                                            {file.name}
                                                        </div>
                                                        <div className="inline-block rounded-full border bg-white/50 px-2 py-1 text-xs text-gray-500">
                                                            {formatFileSize(
                                                                file.size,
                                                            )}
                                                        </div>
                                                        <p className="pt-2 text-xs font-medium text-blue-600">
                                                            Click to change file
                                                        </p>
                                                    </div>
                                                ) : (
                                                    <div className="space-y-3">
                                                        <div className="mx-auto w-fit rounded-full bg-gray-100 p-3 transition-all group-hover:bg-white group-hover:shadow-sm">
                                                            <FileUp className="h-8 w-8 text-gray-400 group-hover:text-gray-600" />
                                                        </div>
                                                        <div>
                                                            <p className="text-sm font-medium text-gray-700">
                                                                Click to upload
                                                            </p>
                                                            <p className="mt-1 text-xs text-gray-500">
                                                                SQL, Dump, GZ,
                                                                BZ2
                                                            </p>
                                                        </div>
                                                    </div>
                                                )}
                                            </label>
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        <div className="flex items-center gap-2 font-semibold text-gray-700">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                                2
                                            </div>
                                            Select Target Database
                                        </div>

                                        <div className="space-y-2">
                                            <Select
                                                value={selectedConnectionId}
                                                onValueChange={
                                                    handleConnectionChange
                                                }
                                                disabled={processing}
                                            >
                                                <SelectTrigger className="h-12 w-full">
                                                    <SelectValue placeholder="Select destination..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {connections.map((conn) => (
                                                        <SelectItem
                                                            key={conn.id}
                                                            value={conn.id.toString()}
                                                        >
                                                            <div className="flex items-center gap-2">
                                                                <Database className="h-4 w-4 text-gray-500" />
                                                                <span className="font-medium">
                                                                    {conn.name}
                                                                </span>
                                                                <span className="text-xs text-muted-foreground">
                                                                    ({conn.host}
                                                                    )
                                                                </span>
                                                            </div>
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <p className="ml-1 text-xs text-muted-foreground">
                                                The backup will be restored to
                                                this connection immediately.
                                            </p>
                                        </div>

                                        <div className="pt-8">
                                            <Button
                                                type="submit"
                                                size="lg"
                                                className="h-12 w-full text-base shadow-sm transition-all hover:shadow"
                                                disabled={
                                                    processing ||
                                                    !file ||
                                                    !selectedConnectionId
                                                }
                                            >
                                                {processing ? (
                                                    <div className="flex items-center gap-2">
                                                        <Loader2 className="h-5 w-5 animate-spin" />
                                                        <span>
                                                            {progress
                                                                ? `Uploading... ${progress.percentage}%`
                                                                : 'Processing Restore...'}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <div className="flex items-center gap-2">
                                                        <Upload className="h-5 w-5" />
                                                        <span>
                                                            Start Restore
                                                            Process
                                                        </span>
                                                    </div>
                                                )}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </form>

                    {progress && (
                        <Card className="animate-in duration-300 fade-in slide-in-from-bottom-4">
                            <CardContent className="pt-6">
                                <div className="space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <span className="font-medium text-blue-600">
                                            Uploading Backup File...
                                        </span>
                                        <span className="text-gray-500">
                                            {progress.percentage}%
                                        </span>
                                    </div>
                                    <Progress
                                        value={progress.percentage}
                                        className="h-2"
                                    />
                                    <p className="pt-1 text-center text-xs text-gray-500">
                                        Please do not close this window while
                                        the file is uploading.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
