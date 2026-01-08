import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Copy, Terminal } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

interface Props {
    restoreId: string;
}

interface RestoreStatus {
    status: 'pending' | 'processing' | 'completed' | 'failed';
    progress: number;
    log: string | null;
    updated_at: string;
}

export default function Output({ restoreId }: Props) {
    const [output, setOutput] = useState<string>('');
    const [status, setStatus] = useState<RestoreStatus | null>(null);
    const outputRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (outputRef.current) {
            outputRef.current.scrollTop = outputRef.current.scrollHeight;
        }
    }, [output]);

    useEffect(() => {
        if (status?.status === 'completed') {
            toast.success('Restore completed successfully!');
        } else if (status?.status === 'failed') {
            toast.error('Restore failed: ' + status.log);
        }
    }, [status]);

    useEffect(() => {
        // Poll for status and output
        const statusInterval = setInterval(() => {
            fetch(`/upload-restore/${restoreId}/status`)
                .then((res) => res.json())
                .then((data) => {
                    setStatus(data);
                })
                .catch((err) => console.error('Failed to fetch status:', err));
        }, 1000);

        const outputInterval = setInterval(() => {
            fetch(`/upload-restore/${restoreId}/output-data`)
                .then((res) => res.json())
                .then((data) => {
                    setOutput(data.output);
                })
                .catch((err) => console.error('Failed to fetch output:', err));
        }, 500);

        // Clear intervals when done
        return () => {
            clearInterval(statusInterval);
            clearInterval(outputInterval);
        };
    }, [restoreId]);

    const handleNewRestore = () => {
        router.visit('/upload-restore');
    };

    const handleBackToUpload = () => {
        router.visit('/upload-restore');
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Upload & Restore', href: '/upload-restore' },
                {
                    title: 'Output',
                    href: `/upload-restore/${restoreId}/output`,
                },
            ]}
        >
            <Head title="Restore Output" />

            <div className="mx-auto max-w-6xl space-y-6 p-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Terminal className="h-5 w-5" />
                            Restore Terminal Output
                        </CardTitle>
                        <CardDescription>
                            Real-time output from the restore process (ID:{' '}
                            {restoreId})
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {status && (
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">
                                        Status:
                                    </span>
                                    <span
                                        className={`text-sm font-semibold ${
                                            status.status === 'completed'
                                                ? 'text-green-600'
                                                : status.status === 'failed'
                                                  ? 'text-red-600'
                                                  : 'text-blue-600'
                                        }`}
                                    >
                                        {status.status}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">
                                        Progress:
                                    </span>
                                    <span className="text-sm font-semibold">
                                        {status.progress}%
                                    </span>
                                </div>
                                <div className="h-2 w-full rounded-full bg-gray-200">
                                    <div
                                        className="h-2 rounded-full bg-blue-600 transition-all duration-300"
                                        style={{ width: `${status.progress}%` }}
                                    />
                                </div>
                                {status.log && (
                                    <div className="rounded border border-red-200 bg-red-50 p-2">
                                        <p className="font-mono text-sm text-red-700">
                                            {status.log}
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}

                        <div
                            ref={outputRef}
                            className="h-96 overflow-y-auto rounded-lg bg-black p-4 font-mono text-sm whitespace-pre-wrap text-green-400"
                        >
                            {output || 'Waiting for output...'}
                        </div>

                        <div className="flex gap-2">
                            <Button
                                onClick={handleBackToUpload}
                                variant="outline"
                            >
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Upload
                            </Button>
                            <Button
                                onClick={() => {
                                    navigator.clipboard.writeText(output);
                                    toast.success('Output copied to clipboard');
                                }}
                            >
                                <Copy className="mr-2 h-4 w-4" />
                                Copy Output
                            </Button>
                            <Button onClick={handleNewRestore}>
                                New Restore
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
