import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { destroy, index, store, update } from '@/routes/backup-disks';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface BackupDisk {
    id: number;
    name: string;
    driver: string;
    config: any;
    is_default: boolean;
    is_active: boolean;
}

export default function BackupDisks({ disks }: { disks: BackupDisk[] }) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingDisk, setEditingDisk] = useState<BackupDisk | null>(null);

    const { data, setData, processing, errors, clearErrors, reset } = useForm({
        name: '',
        driver: 'local',
        is_default: false,
        is_active: true,
        root: '',
        key: '',
        secret: '',
        region: '',
        bucket: '',
        endpoint: '',
    });

    const openCreateDialog = () => {
        setEditingDisk(null);
        reset();
        clearErrors();
        setIsDialogOpen(true);
    };

    const openEditDialog = (disk: BackupDisk) => {
        setEditingDisk(disk);
        setData({
            name: disk.name,
            driver: disk.driver,
            is_default: disk.is_default,
            is_active: disk.is_active,
            root: disk.config.root || '',
            key: disk.config.key || '',
            secret: disk.config.secret || '',
            region: disk.config.region || '',
            bucket: disk.config.bucket || '',
            endpoint: disk.config.endpoint || '',
        });
        clearErrors();
        setIsDialogOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        // Construct config object based on driver
        const configData: Record<string, any> = {};
        if (data.driver === 'local') {
            configData.root = data.root;
        } else {
            configData.key = data.key;
            configData.secret = data.secret;
            configData.region = data.region;
            configData.bucket = data.bucket;
            if (data.endpoint) configData.endpoint = data.endpoint;
        }

        const payload = {
            name: data.name,
            driver: data.driver,
            config: configData,
            is_default: data.is_default,
            is_active: data.is_active,
        };

        if (editingDisk) {
            router.put(update(editingDisk.id).url, payload, {
                onSuccess: () => setIsDialogOpen(false),
            });
        } else {
            router.post(store().url, payload, {
                onSuccess: () => setIsDialogOpen(false),
            });
        }
    };

    const deleteDisk = (disk: BackupDisk) => {
        if (confirm('Are you sure you want to delete this disk?')) {
            router.delete(destroy(disk.id).url);
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Backup Disks', href: index().url }]}>
            <Head title="Backup Disks" />
            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <HeadingSmall
                            title="Backup Disks"
                            description="Configure where your database backups are stored."
                        />
                        <Button onClick={openCreateDialog} size="sm">
                            <Plus className="mr-2 h-4 w-4" />
                            Add Disk
                        </Button>
                    </div>

                    <div className="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Driver</TableHead>
                                    <TableHead>Default</TableHead>
                                    <TableHead>Active</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {disks.map((disk) => (
                                    <TableRow key={disk.id}>
                                        <TableCell className="font-medium">
                                            {disk.name}
                                        </TableCell>
                                        <TableCell>{disk.driver}</TableCell>
                                        <TableCell>
                                            {disk.is_default ? (
                                                <Check className="h-4 w-4 text-green-500" />
                                            ) : null}
                                        </TableCell>
                                        <TableCell>
                                            {disk.is_active ? (
                                                <Check className="h-4 w-4 text-green-500" />
                                            ) : (
                                                <X className="h-4 w-4 text-red-500" />
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        openEditDialog(disk)
                                                    }
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-red-500 hover:text-red-600"
                                                    onClick={() =>
                                                        deleteDisk(disk)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {disks.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="py-4 text-center text-muted-foreground"
                                        >
                                            No backup disks configured.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {editingDisk ? 'Edit Disk' : 'Add Disk'}
                            </DialogTitle>
                            <DialogDescription>
                                Configure the storage disk settings.
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="driver">Driver</Label>
                                <Select
                                    value={data.driver}
                                    onValueChange={(val) =>
                                        setData('driver', val)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="local">
                                            Local
                                        </SelectItem>
                                        <SelectItem value="s3">S3</SelectItem>
                                        <SelectItem value="digitalocean">
                                            DigitalOcean Spaces
                                        </SelectItem>
                                        <SelectItem value="wasabi">
                                            Wasabi
                                        </SelectItem>
                                        <SelectItem value="backblaze">
                                            Backblaze B2
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.driver} />
                            </div>

                            {data.driver === 'local' ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="root">Root Path</Label>
                                    <Input
                                        id="root"
                                        value={data.root}
                                        onChange={(e) =>
                                            setData('root', e.target.value)
                                        }
                                        placeholder="/path/to/backups"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Absolute path or relative to storage/app
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="key">Key</Label>
                                        <Input
                                            id="key"
                                            value={data.key}
                                            onChange={(e) =>
                                                setData('key', e.target.value)
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="secret">Secret</Label>
                                        <Input
                                            id="secret"
                                            type="password"
                                            value={data.secret}
                                            onChange={(e) =>
                                                setData(
                                                    'secret',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="region">Region</Label>
                                        <Input
                                            id="region"
                                            value={data.region}
                                            onChange={(e) =>
                                                setData(
                                                    'region',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="bucket">Bucket</Label>
                                        <Input
                                            id="bucket"
                                            value={data.bucket}
                                            onChange={(e) =>
                                                setData(
                                                    'bucket',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="endpoint">
                                            Endpoint (Optional)
                                        </Label>
                                        <Input
                                            id="endpoint"
                                            value={data.endpoint}
                                            onChange={(e) =>
                                                setData(
                                                    'endpoint',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </>
                            )}

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_default"
                                    checked={data.is_default}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'is_default',
                                            checked as boolean,
                                        )
                                    }
                                />
                                <Label htmlFor="is_default">
                                    Set as Default Disk
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', checked as boolean)
                                    }
                                />
                                <Label htmlFor="is_active">Active</Label>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsDialogOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </SettingsLayout>
        </AppLayout>
    );
}
